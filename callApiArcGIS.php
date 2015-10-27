<?php
/**
 * htmlEditorAnswers Plugin for LimeSurvey
 *
 * @author Denis Chenu <denis@sondages.pro>
 * @copyright 2014-2015 Denis Chenu <http://sondages.pro>
 * @copyright 2014-2015 WHO | World Health Organization <http://www.who.int>
 * @license GNU AFFERO GENERAL PUBLIC LICENSE Version 3 or later (the "AGPL")
 * @version 1.0
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 */
class callApiArcGIS extends PluginBase {
    protected $storage = 'DbStorage';
    
    static protected $description = 'Call API ARcGis for admin1,2,.. information .';
    static protected $name = 'callApiArcGIS';

    protected $settings = array(
        'ApiArcGISbaseUrl' => array(
            'type' => 'string',
            'label' => 'The API base URL .',
            'default'=>'https://gistmaps.itos.uga.edu/arcgis/rest/services/COD_External/',
        ),
        'activateForQuestionCode' => array(
            'type' => 'string',
            'label' => 'Activate for question code, question type must be  admin1, admin2 .....',
            'default'=>'arcgis',
        ),
    );
    private $default=array(
        'countryCode'=>"SY",
        'countryUrl'=>"SYR_pcode",
        'admin0Url'=>'',
        'admin1Url'=>'2',
        'admin2Url'=>'3',
        'admin3Url'=>'4',
        'admin4Url'=>'5',
        'admin5Url'=>'',
        'pcodeUrl'=>'0',
    );
    public function __construct(PluginManager $manager, $id) {
        parent::__construct($manager, $id);
        $this->subscribe('beforeSurveySettings');
        $this->subscribe('newSurveySettings');
        $this->subscribe('beforeQuestionRender');
        $this->subscribe('newDirectRequest');

    }

    public function newDirectRequest()
    {
        $oEvent = $this->event;
        $sAction=$oEvent->get('function');
        if ($oEvent->get('target') == "callApiArcGIS")
        {
            if($sAction=='arcgis')
                $this->actionArcgis();
            else
                throw new CHttpException(404,'Unknow action');
        }
    }

    public function actionArcgis()
    {
        $oEvent = $this->event;
        $iSurveyId=Yii::app()->session['LEMsid'];
        $oEvent->set('surveyId',$iSurveyId);
        $sLang=Yii::app()->session['LEMlang'];
        $adminLevel=Yii::app()->request->getParam('level',1);
        $levelWhere=Yii::app()->request->getParam('where');
        $levelCode=Yii::app()->request->getParam('code');
        if(!$levelWhere)
        {
            $levelWhere="admin0";
            $levelCode=$this->get('countryCode', 'Survey', $iSurveyId,$this->default['countryCode']);
        }
        $thisUrl=$this->getApiUrl($adminLevel,$levelWhere,$levelCode);
        if($thisUrl)
        {
            $jsonInfo=file_get_contents($thisUrl);
            $aOptions=array();

            $oInfo=json_decode($jsonInfo);
            if($oInfo && isset($oInfo->features))
            {
                foreach($oInfo->features as $oOptions)
                {
                    $oInformations=$oOptions->attributes;
                    $sText="";
                    $attributeLang="{$adminLevel}Name_{$sLang}";
                    $attributeDefault="{$adminLevel}RefName";
                    $altAttributeLang="featureName_{$sLang}";
                    $altAttributeDefault="featureRefName";
                    $attributeKey="{$adminLevel}Pcode";
                    $altAttributeKey="pcode";

                    if(isset($oInformations->$attributeLang))
                        $sText=$oInformations->$attributeLang;
                    elseif(isset($oInformations->$attributeDefault))
                        $sText=$oInformations->$attributeDefault;
                    elseif(isset($oInformations->$altAttributeLang))
                        $sText=$oInformations->$altAttributeLang;
                    elseif(isset($oInformations->$altAttributeDefault))
                        $sText=$oInformations->$altAttributeDefault;
                    if($sText)
                    {
                        if(isset($oInformations->$attributeKey))
                            $aOptions[$oInformations->$attributeKey]=$sText;
                        elseif(isset($oInformations->$altAttributeKey))
                            $aOptions[$oInformations->$altAttributeKey]=$sText;
                    }
                }
            }
        }
        else{
          $this->displayJson(array(
            "status"=>"error",
            "adminLevel"=>$adminLevel,
            "levelWhere"=>$levelWhere,
            "levelCode"=>$levelCode,
          ));
        }
        // Add Capital
        $thisUrl=$this->getApiUrl("admin0",$levelWhere,$levelCode);
        if($thisUrl)
        {
          $jsonInfo=file_get_contents($thisUrl);
          $oInfo=json_decode($jsonInfo);
          if($oInfo && isset($oInfo->features))
          {
              foreach($oInfo->features as $oOptions)
              {
                  $oInformations=$oOptions->attributes;
                  $sText="";
                  $attributeLang="admin{$adminLevel}Name_{$sLang}";
                  $attributeDefault="admin{$adminLevel}RefName";
                  $attributeKey="admin{$adminLevel}Pcode";

                  if(isset($oInformations->$attributeLang))
                      $sText=$oInformations->$attributeLang;
                  elseif(isset($oInformations->$attributeDefault))
                      $sText=$oInformations->$attributeDefault;
                  if($sText && isset($oInformations->$attributeKey))
                      $aOptions[$oInformations->$attributeKey]=$sText;
              }
          }
        }
        asort($aOptions);
        $this->displayJson($aOptions);
    }

    private function displayJson($aArray)
    {
        //~ Yii::import('application.helpers.viewHelper');
        //~ viewHelper::disableHtmlLogging();
        header('Content-type: application/json');
        echo json_encode($aArray);
        Yii::app()->end();
    }

    public function beforeSurveySettings()
    {
        $oEvent = $this->event;
        $assetUrl = Yii::app()->assetManager->publish(dirname(__FILE__) . '/assets');
        Yii::app()->clientScript->registerCssFile($assetUrl . '/settingsfix.css');

        $htmlList=array();

        if($thisUrl=$this->getApiUrl("admin0","admin0",$this->get('countryCode', 'Survey',$oEvent->get('survey'),$this->default['countryCode'])))
            $htmlList[]=CHtml::tag("li",array(),"<span class='label'>admin0 link</span> : <a href='".$thisUrl."' target='_blank'>".$thisUrl."</a>");
        if($thisUrl=$this->getApiUrl("admin1","admin0",$this->get('countryCode', 'Survey',$oEvent->get('survey'),$this->default['countryCode'])))
            $htmlList[]=CHtml::tag("li",array(),"<span class='label'>admin1 link</span> : <a href='".$thisUrl."' target='_blank'>".$thisUrl."</a>");
        if($thisUrl=$this->getApiUrl("admin2","admin0",$this->get('countryCode', 'Survey', $oEvent->get('survey'),$this->default['countryCode'])))
            $htmlList[]=CHtml::tag("li",array(),"<span class='label'>admin2 link</span> : <a href='".$thisUrl."' target='_blank'>".$thisUrl."</a>");
        if($thisUrl=$this->getApiUrl("admin3","admin0",$this->get('countryCode', 'Survey', $oEvent->get('survey'),$this->default['countryCode'])))
            $htmlList[]=CHtml::tag("li",array(),"<span class='label'>admin3 link</span> : <a href='".$thisUrl."' target='_blank'>".$thisUrl."</a>");
        if($thisUrl=$this->getApiUrl("admin4","admin0",$this->get('countryCode', 'Survey', $oEvent->get('survey'),$this->default['countryCode'])))
            $htmlList[]=CHtml::tag("li",array(),"<span class='label'>admin4 link</span> : <a href='".$thisUrl."' target='_blank'>".$thisUrl."</a>");
        if($thisUrl=$this->getApiUrl("admin5","admin0",$this->get('countryCode', 'Survey', $oEvent->get('survey'),$this->default['countryCode'])))
            $htmlList[]=CHtml::tag("li",array(),"<span class='label'>admin5 link</span> : <a href='".$thisUrl."' target='_blank'>".$thisUrl."</a>");
        if($thisUrl=$this->getApiUrl("pcode","admin0",$this->get('countryCode', 'Survey', $oEvent->get('survey'),$this->default['countryCode'])))
            $htmlList[]=CHtml::tag("li",array(),"<span class='label'>Populated place link</span> : <a href='".$thisUrl."' target='_blank'>".$thisUrl."</a>");
        $thisUrl=$this->api->createUrl('plugins/direct', array('plugin' => get_class($this),'function' => 'arcgis'));
        $htmlList[]=CHtml::tag("li",array(),"<span class='label'>directLink</span> : <a href='".$thisUrl."' target='_blank'>".$thisUrl."</a>");

        $sHtmlInfo=CHtml::tag("ul",array(),implode($htmlList));
        $oEvent->set("surveysettings.{$this->id}", array(
            'name' => get_class($this),
            'settings' => array(
                'countryCode'=> array(
                    'type' => 'string',
                    'label' => 'Admin1 restrict to Country code for admin0',
                    'current'=>$this->get('countryCode', 'Survey', $oEvent->get('survey'),$this->default['countryCode']),
                ),
                'countryUrl'=> array(
                    'type' => 'string',
                    'label' => 'Contry complement url for country',
                    'current'=>$this->get('countryUrl', 'Survey', $oEvent->get('survey'),$this->default['countryUrl']),
                ),
                'admin0Url'=> array(
                    'type' => 'string',
                    'label' => 'Database number for capital (to be added to admin1,2 ...)',
                    'current'=>$this->get('admin0Url', 'Survey', $oEvent->get('survey'),$this->default['admin0Url']),
                ),
                'admin1Url'=> array(
                    'type' => 'string',
                    'label' => 'Database number for admin1',
                    'current'=>$this->get('admin1Url', 'Survey', $oEvent->get('survey'),$this->default['admin1Url']),
                ),
                'admin2Url'=> array(
                    'type' => 'string',
                    'label' => 'Database number for admin2',
                    'current'=>$this->get('admin2Url', 'Survey', $oEvent->get('survey'),$this->default['admin2Url']),
                ),
                'admin3Url'=> array(
                    'type' => 'string',
                    'label' => 'Database number for admin3',
                    'current'=>$this->get('admin3Url', 'Survey', $oEvent->get('survey'),$this->default['admin3Url']),
                ),
                'admin4Url'=> array(
                    'type' => 'string',
                    'label' => 'Database number for admin4',
                    'current'=>$this->get('admin4Url', 'Survey', $oEvent->get('survey'),$this->default['admin4Url']),
                ),
                'admin5Url'=> array(
                    'type' => 'string',
                    'label' => 'Database number for admin5',
                    'current'=>$this->get('admin5Url', 'Survey', $oEvent->get('survey'),$this->default['admin5Url']),
                ),
                'pcodeUrl'=> array(
                    'type' => 'string',
                    'label' => 'Database number for populated place (if needed)',
                    'current'=>$this->get('pcodeUrl', 'Survey', $oEvent->get('survey'),$this->default['pcodeUrl']),
                ),
                'infoUrls'=>array(
                    'type'=>'info',
                    'content'=>"<div class='alert alert-info'>{$sHtmlInfo}</div>",
                ),
            ),
        ));
    }

    public function newSurveySettings()
    {
        $oEvent = $this->event;
        foreach ($oEvent->get('settings') as $name => $value)
        {
            /* In order use survey setting, if not set, use global, if not set use default */
            $default=$oEvent->get($name,null,null,isset($this->settings[$name]['default'])?$this->settings[$name]['default']:NULL);
            $this->set($name, $value, 'Survey', $oEvent->get('survey'),$default);
        }
    }

    public function beforeQuestionRender()
    {
        $oEvent=$this->getEvent();
        $sQuestionType="Q";
        $sQuestionCode=$this->get('activateForQuestionCode',null,null,$this->settings['activateForQuestionCode']['default']);
        if($oEvent->get('type')==$sQuestionType && $oEvent->get('code')==$sQuestionCode)
        {
            $iSurveyId=$oEvent->get('surveyId');
            $oEvent->set('class',$oEvent->get('class')." arcgis-question");
            $sScriptFile=Yii::app()->baseUrl.'/plugins/htmlEditorAnswers/third_party/ckeditor/ckeditor.js'; // Allow preview
            // Some css correction (with asset)
            $jsAssetUrl = Yii::app()->assetManager->publish(dirname(__FILE__) . '/assets/arcgis.js');
            $cssAssetUrl = Yii::app()->assetManager->publish(dirname(__FILE__) . '/assets/css/');
            Yii::app()->clientScript->registerScriptFile($jsAssetUrl,CClientScript::POS_HEAD);
            Yii::app()->clientScript->registerCssFile($cssAssetUrl."/arcgis.css");
            $sArcgisScript = "var arcgisUrl='".$this->api->createUrl('plugins/direct', array('plugin' => get_class($this),'function' => 'arcgis'))."';\n";
            $sArcgisScript.= "var pleaseChoose='".gt("Please choose")."';\n";

            if($oEvent->get('gid'))
                $answerId="answer{$oEvent->get('surveyId')}X{$oEvent->get('gid')}X{$oEvent->get('qid')}";
            else
            {
                $oQuestion=Question::model()->find("qid=:qid",array(":qid"=>$oEvent->get('qid')));
                $answerId="answer{$oEvent->get('surveyId')}X{$oQuestion->gid}X{$oEvent->get('qid')}";

            }
            $sArcgisScript.="findArcGis({$oEvent->get('qid')},'{$answerId}')";

            Yii::app()->clientScript->registerScript("sArcgisScript",$sArcgisScript,CClientScript::POS_END);
        }
    }

    private function getApiUrl($adminLevel,$selectAdminlevel,$selectAdminlevelCode,$return="pjson")
    {
        $oEvent = $this->event;
        $iSurveyId=$oEvent->get('survey');
        if(!$iSurveyId)
            $iSurveyId=$oEvent->get('surveyId');
        if(!$iSurveyId)
            return;
        if($this->get('countryUrl', 'Survey', $iSurveyId,$this->default['countryUrl'])=="")
            return;

        $ApiArcGISbaseUrl=$this->get('ApiArcGISbaseUrl',null,null,$this->settings['ApiArcGISbaseUrl']['default']);
        $ApiArcGISbaseUrl.=$this->get('countryUrl', 'Survey', $iSurveyId,$this->default['countryUrl'])."/MapServer/";

        $adminLevelUrl=$this->get($adminLevel."Url", 'Survey', $iSurveyId,$this->default[$adminLevel."Url"]);
        if($adminLevelUrl=="")
            return;

        $baseArray=array(
            "where"=>"{$selectAdminlevel}Pcode='{$selectAdminlevelCode}'",
            "outFields"=>"*",
            "returnGeometry"=>"false",
            "returnDistinctValues"=>"false",
            "f"=>$return,
        );
        $thisUrl=$ApiArcGISbaseUrl.$adminLevelUrl."/query?";
    
        $thisUrl .= http_build_query($baseArray);

        return $thisUrl;
    }
    private function setLanguage()
    {
        // we need some replacement (for exemple de- => de)
    }
    public function saveSettings($settings)
    {

    }
    public function getPluginSettings($getValues = true)
    {
        $assetUrl = Yii::app()->assetManager->publish(dirname(__FILE__) . '/assets');
        Yii::app()->clientScript->registerCssFile($assetUrl . '/settingsfix.css');
        return parent::getPluginSettings($getValues);

    }
}


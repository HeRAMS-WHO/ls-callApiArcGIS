var argis={
    "input": [
        "admin1",
        "admin2",
        "admin3",
        "admin4",
        "admin5",
        "pcode"
    ]
};

function findArcGis(qId,answerId)
{
  var firstLevel=false;
  // Hide the answer text
  jQuery.each(argis.input, function(index, level){
    // first level
    if(!firstLevel && $("#"+answerId+level).length){
      firstLevel = level;
    }
    $("#"+answerId+level).hide();
  });
  // Always replace first
  doSelect(answerId,firstLevel,0,0);
}

function doSelect(answerId,doLevel,searchLevel,searchCode)
{

  if(typeof searchCode=="undefined" || !searchCode)
   searchCode="";
  $("#"+answerId+doLevel).closest(".answer-item").addClass("search");
  if(searchCode=="" && $("#"+answerId+doLevel).val()=="")
  {
    $("#select"+answerId+doLevel).remove();
    $("#"+answerId+doLevel).val("");
    jQuery.each(argis.input, function(index, level)
    {
      $("#"+answerId+level).val("").trigger("keyup");
      $("#select"+answerId+level).remove();
    });
      //return;
  }
var baseIputName=answerId+doLevel;
var isUp=false;
var selectToUse=false;
  $.ajax({
    url: arcgisUrl,
    dataType: "json",
    data: {
      level : doLevel,
      where : searchLevel,
      code : searchCode
    }
  })
  .success(function( data ) {
    $("#select"+baseIputName).remove();
    var items = [];
    $.each( data, function( key, val ) {
      items.push( "<option value='" + key + "'>" + val + "</option>" );
    });
    if(items.length > 0)
    {
      var selectVal="";
      items.unshift( "<option value=''>" + pleaseChoose + "</option>" );
      $( "<select/>", {
        'id' : "select"+baseIputName,
        'class' : 'arcgisdropdown',
        'data-update' : baseIputName,
        'data-level' : doLevel,
        'data-answerid' : answerId,
        html: items.join( "" )
      }).insertAfter("#"+baseIputName);
      if(items.length==2)
      {
        selectVal=$("#select"+baseIputName).find("option[value!='']").attr('value');
        $("#select"+baseIputName).val(selectVal).trigger("change");
      }
      if(selectVal=="" && $("#"+baseIputName).val()!="")
      {
        selectVal=$("#"+baseIputName).val().trim();
        if(selectVal!='none' && $("#select"+baseIputName).find("option[value='"+selectVal+"']").length)
        {
          $("#select"+baseIputName).val(selectVal).trigger("change");
        }else{
            selectVal="";
        }
      }
      if(selectVal==""){
          $("#"+answerId+baseIputName).val("").trigger("keyup");
          //$("#select"+answerId+"admin"+doLevel).val("").trigger("change");
          $("#select"+baseIputName+" option:first").prop('selected',true);
          isUp=false;
          jQuery.each(argis.input, function(index, level)
          {
            if(isUp)
            {
              $("#select"+answerId+level).remove();
              $("#"+answerId+level).val("").trigger("keyup");
            }
            else if(level==doLevel)
            {
              isUp=true;
            }
          });
      }
    }
    else
    {
      $("#"+baseIputName).val("none").trigger("keyup");
      selectToUse=false;
      isUp=false;
      jQuery.each(argis.input, function(index, level)
      {
        if(!isUp && $("#select"+answerId+level).length)
        {
          selectToUse=$("#select"+answerId+level);
        }
        if(isUp && $("#"+answerId+level).length && selectToUse)
        {
          doSelect(answerId,level,$(selectToUse).data('level'),$(selectToUse).val());
          return false;
        }
        if(level==doLevel)
        {
          isUp=true;
        }
      });
    }
    $("#"+baseIputName).closest(".answer-item").removeClass("search");
  });
}
$(document).on("change","select.arcgisdropdown[data-update]",function(){

  $("#"+$(this).data("update")).val($(this).val()).trigger("keyup");
  if($(this).data("level")=="pcode"){
    return;
  }
//  var nextLevel=$(this).data("level")+1;
  var answerId=$(this).data("answerid");
  var activeLevel=$(this).data("level");
  var activeCode=$(this).val();
  var isUp=false;
  var isDone=false;
  jQuery.each(argis.input, function(index, level)
  {
    if(isUp)
    {
      if($("#"+answerId+level).length)
      {
        doSelect(answerId,level,activeLevel,activeCode);
        return false;
      }
    }
    else if(level==activeLevel)
    {
      isUp=true;
    }
  });
});


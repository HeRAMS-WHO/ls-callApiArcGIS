function findArcGis(qId,answerId)
{
  // Hide the answer text
  for (i = 0; i < 7; i++) {
    $("#"+answerId+"admin"+i).hide();
  }
  // Always replace first
  doSelect(answerId,1,0,0);
}

function doSelect(answerId,doLevel,searchLevel,searchCode)
{

  if(typeof searchCode=="undefined" || !searchCode)
   searchCode="";
  $("#"+answerId+"admin"+doLevel).closest(".answer-item").addClass("search");
  if(searchCode=="" && $("#"+answerId+"admin"+doLevel).val()=="")
  {
    $("#select"+answerId+"admin"+doLevel).remove();
    $("#"+answerId+"admin"+doLevel).val("");
    for (i = doLevel+1; i < 7; i++) {
      $("#"+answerId+"admin"+i).val("").trigger("keyup");
      $("#select"+answerId+"admin"+i).remove();
    }
      //return;
  }

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
    $("#select"+answerId+"admin"+doLevel).remove();
    var items = [];
    $.each( data, function( key, val ) {
      items.push( "<option value='" + key + "'>" + val + "</option>" );
    });
    if(items.length > 0)
    {
      var selectVal="";
      items.unshift( "<option value=''>" + pleaseChoose + "</option>" );
      $( "<select/>", {
        'id' : "select"+answerId+"admin"+doLevel,
        'class' : 'arcgisdropdown',
        'data-update' : answerId+"admin"+doLevel,
        'data-level' : doLevel,
        'data-answerid' : answerId,
        html: items.join( "" )
      }).insertAfter("#"+answerId+"admin"+doLevel);
      if(items.length==2)
      {
        selectVal=$("#select"+answerId+"admin"+doLevel).find("option[value!='']").attr('value');
        $("#select"+answerId+"admin"+doLevel).val(selectVal).trigger("change");
      }
      if(selectVal=="" && $("#"+answerId+"admin"+doLevel).val()!="")
      {
        selectVal=$("#"+answerId+"admin"+doLevel).val().trim();
        if(selectVal!='none' && $("#select"+answerId+"admin"+doLevel).find("option[value='"+selectVal+"']").length)
        {
          $("#select"+answerId+"admin"+doLevel).val(selectVal).trigger("change");
        }else{
            selectVal="";
        }
      }
      if(selectVal==""){
          $("#"+answerId+"admin"+doLevel).val("").trigger("keyup");
          //$("#select"+answerId+"admin"+doLevel).val("").trigger("change");
          $("#select"+answerId+"admin"+doLevel+" option:first").prop('selected',true);
          for (i = doLevel+1; i < 7; i++) {
            $("#select"+answerId+"admin"+i).remove();
            $("#"+answerId+"admin"+i).val("").trigger("keyup");
          }
      }
    }
    else
    {
      $("#"+answerId+"admin"+doLevel).val("none").trigger("keyup");
      for (i = doLevel+1; i < 7; i++) {
        $("#"+answerId+"admin"+i).val("none").trigger("keyup");
      }
    }
    $("#"+answerId+"admin"+doLevel).closest(".answer-item").removeClass("search");
  });
}
$(document).on("change","select.arcgisdropdown[data-update]",function(){
  $("#"+$(this).data("update")).val($(this).val()).trigger("keyup");
  var nextLevel=$(this).data("level")+1;
  var answerId=$(this).data("answerid");
  while (nextLevel < 7 && $("#"+answerId+"admin"+nextLevel).length==0) {
    nextLevel++;

  }
  if($("#"+answerId+"admin"+nextLevel).length)
  {
      doSelect(answerId,nextLevel,$(this).data("level"),$(this).val());
      //return;
  }
});

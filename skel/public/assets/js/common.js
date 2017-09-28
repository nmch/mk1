$(function(){
	$(".pagination a").on('click',function(){
		if( $(this).parents('li:first').is('.disabled') ){
			return false;
		}
	    var target_page = $(this).attr('href');
		
	    var form = $("form.with-pager");
	    form.find("input[name=page]").val( target_page );
	    form.submit();
		
	    return false;
	});
	
	$('.datepicker').datepicker({
		language          : 'ja',
		format            : 'yyyy-mm-dd',
		todayBtn          : "linked",
		keyboardNavigation: false,
		forceParse        : false,
		calendarWeeks     : true,
		autoclose         : true
	});
});

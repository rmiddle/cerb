<script type="text/javascript">
var $width = $(window).width()-100;
var $window = genericAjaxPopup('search_results','c=search&a=openSearchPopup&context={$context}&q={$q|escape:"url"}', null, false, $width);
$window.closest('.ui-dialog')
	.hide()
	.fadeIn()
;
</script>

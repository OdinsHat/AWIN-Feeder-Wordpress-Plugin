jQuery(document).ready(function(){
    var oTable = jQuery('#products-table').dataTable({
        'bProcessing': true,
        'bServerSide': true,
        'sAjaxSource': ajaxurl,
        "fnServerParams": function(aoData){
            aoData.push({'name':'action', 'value':'aw_json_prod'});
        },
        'aoColumnDefs': [
            {'sWidth': '5%', 'aTargets': [0]}
        ],
        "fnDrawCallback": function() {
            jQuery("#products-table tbody tr td a.aw-prod-del").click(function () { 
                var id = jQuery(this).attr('id');
                jQuery.post(ajaxurl, {'action': 'aw_del_prod', 'id': id});
                jQuery('#row_' + id).fadeOut();
            });
        }
    });
});

jQuery(document).ready(function(){
    jQuery('#products-table').dataTable({
        'bProcessing': true,
        'bServerSide': true,
        'sAjaxSource': ajaxurl,
        "fnServerParams": function(aoData){
            console.log(aoData);
            aoData.push({'name':'action', 'value':'json_products'});
        },
        'aoColumnDefs': [
            {'sWidth': '5%', 'aTargets': [0]}
        ]
    });
});

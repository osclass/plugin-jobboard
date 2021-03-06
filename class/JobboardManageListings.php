<?php if ( !defined('ABS_PATH') ) exit('ABS_PATH is not loaded. Direct access is not allowed.');

/*
 * Modify Manage Listings/Vacancies at oc-admin
 */
class JobboardManageListings
{
    public function __construct() {

        osc_add_filter('admin_body_class', array(&$this, 'add_item_body_class'));

        osc_add_hook('admin_header',            array(&$this, 'jobboard_delete_dialog_js'), 6);
        // modify manage listings table header
        osc_add_hook('admin_items_table',       array(&$this, 'job_items_table_header') );
        osc_add_filter("items_processing_row",  array(&$this, "job_items_row") );
        // modify actions/more-actions (manage listing row)
        osc_add_filter('more_actions_manage_items', array(&$this, 'jobboard_more_options') );
        osc_add_filter('actions_manage_items',      array(&$this, 'jobboard_manage_actions') );

        osc_add_filter('admin_dialog_delete_listing_text', array(&$this, 'jobboard_dialog_delete_text'));
    }

    function jobboard_delete_dialog_js()
    {
        ?>
    <script>
        $(document).ready(function(){
            $("#dialog-item-delete").bind("dialogopen", function(event, ui) {
                $("#dialog-item-delete").dialog('option', "width", 660);
            });
        });
    </script>
        <?php
    }

    function jobboard_dialog_delete_text($str)
    {
        return __('Are you sure? If you delete this offer, all the candidates\' CV that have applied for this job will be automatically deleted and you will no longer have access to that data. If you don\'t want this job offer to be displayed, but still keep all the CV, it\'s better you select "Block" option.', 'jobboard');
    }

    /**
     * Modify table with custom header
     * @param type $table
     */
    function job_items_table_header($table) {
        $table->addColumn("mod_date", __("Modified", "jobboard"));
        $table->addColumn("applicants", __("# of applicants", "jobboard"));
        $table->addColumn("views", __("Views", "jobboard"));
        $table->removeColumn("user");
        $table->removeColumn("category");
    }


    /**
     * Add new fields to the table
     *
     * @param type $row
     * @param type $aRow
     * @return type
     */
    function job_items_row($row, $aRow) {
        list($applicants, $total) = ModelJB::newInstance()->searchCount(array('item' => $aRow['pk_i_id']));

        $str_closed = '';
        if($aRow['b_active']==0 || $aRow['b_enabled']==0) {
            $str_closed = '<span class="closed">'.__('Closed', 'jobboard').'</span>';
        }

        $row['title'] = $row['title']. ' ' . $str_closed;
        $row['mod_date'] = @$aRow['dt_mod_date'];
        $row['applicants'] = '<a href="' . osc_admin_render_plugin_url("jobboard/people.php&jobId=") . $aRow['pk_i_id'] . '">' . sprintf(__('%d applicants', 'jobboard'), $applicants) . '</a>';
        $views = 0;
        if( @$aRow['i_num_views'] > 0 ) {
            $views = $aRow['i_num_views'];
        }
        $row['views'] = @$aRow['i_num_views'];
        return $row;
    }

    function jobboard_more_options($options, $aRow) {
        return array();
    }

    function jobboard_manage_actions($options, $aRow) {
        $csrf_token_url = osc_csrf_token_url();

        if($aRow['b_enabled']) {
            $options[] = '<a href="' . osc_admin_base_url(true) . '?page=items&amp;action=status&amp;id=' . $aRow['pk_i_id'] . '&amp;' . $csrf_token_url . '&amp;value=DISABLE">' . __('Block', 'jobboard') .'</a>' ;
        } else {
            $options[] = '<a href="' . osc_admin_base_url(true) . '?page=items&amp;action=status&amp;id=' . $aRow['pk_i_id'] . '&amp;' . $csrf_token_url . '&amp;value=ENABLE">' . __('Unblock', 'jobboard') .'</a>' ;
        }
        $options[] = '<a href="' . osc_admin_base_url(true) . '?page=items&amp;action=post&amp;duplicatefrom=' . $aRow['pk_i_id'] . '">' . __('Duplicate', 'jobboard') . '</a>' ;
        return $options;
    }

    function add_item_body_class($content) {
    if(urldecode(Params::getParam('page')) === 'items' && Params::getParam('action') === '' ) {
            $content[] = 'items';
    }

    return $content;
    }


}
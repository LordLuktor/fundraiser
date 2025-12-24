<?php
namespace FundraiserPro;
class AjaxHandler {
	public function load_more_campaigns() { wp_send_json_success( array( "html" => "", "has_more" => false ) ); }
	public function get_campaign_stats() { wp_send_json_success( array() ); }
}

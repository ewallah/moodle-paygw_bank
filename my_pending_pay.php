<?php
use core_payment\helper;
use paygw_bank\bank_helper;

require_once __DIR__ . '/../../../config.php';
require_once './lib.php';
require_login();

$context = context_system::instance(); // Because we "have no scope".
$PAGE->set_context(context_user::instance($USER->id));
$canuploadfiles = get_config('paygw_bank', 'usercanuploadfiles');
$PAGE->set_url('/payment/gateway/bank/my_pending_pay.php', $params);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('my_pending_payments', 'paygw_bank'));
$PAGE->navigation->extend_for_user($USER->id);
$PAGE->set_heading(get_string('my_pending_payments', 'paygw_bank'));
$PAGE->navbar->add(get_string('profile'), new moodle_url('/user/profile.php', array('id' => $USER->id)));
$PAGE->navbar->add(get_string('my_pending_payments', 'paygw_bank'));
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('my_pending_payments', 'paygw_bank'), 2);
$bankentries = bank_helper::get_user_pending($USER->id);
if (!$bankentries) {
    $match = array();
    echo $OUTPUT->heading(get_string('noentriesfound', 'paygw_bank'));
    $table = null;

} else
{
    $table = new html_table();
    $canuploadfiles = get_config('paygw_bank', 'usercanuploadfiles');
    $headarray = array(get_string('date'), get_string('code', 'paygw_bank'), get_string('concept', 'paygw_bank'), get_string('total_cost', 'paygw_bank'), get_string('currency'));
    if($canuploadfiles) {
        array_push($headarray, get_string('hasfiles', 'paygw_bank'));
    }
    array_push($headarray, get_string('actions'));
    $table->head = $headarray;
    foreach($bankentries as $bankentry)
    {
        $config = (object) helper::get_gateway_configuration($bankentry->component, $bankentry->paymentarea, $bankentry->itemid, 'bank');
        $payable = helper::get_payable($bankentry->component, $bankentry->paymentarea, $bankentry->itemid);
        $currency = $payable->get_currency();
        $customer = $DB->get_record('user', array('id' => $bankentry->userid));
        $fullname = fullname($customer, true);

        // Add surcharge if there is any.
        $surcharge = helper::get_gateway_surcharge('paypal');
        $amount = helper::get_rounded_cost($payable->get_amount(), $currency, $surcharge);
        $component = $bankentry->component;
        $paymentarea = $bankentry->paymentarea;
        $itemid = $bankentry->itemid;
        $description = $bankentry->description;
        $urlpay = new moodle_url('/payment/gateway/bank/pay.php', array('component' => $component, 'paymentarea' => $paymentarea, 'itemid' => $itemid, 'description' => $description));
        $buttongo = '<a class="btn btn-primary" href="'.$urlpay.'">'.get_string('go').'</a>';
        $dataarray = array(date('Y-m-d', $bankentry->timecreated), $bankentry->code, $bankentry->description,
        $amount, $currency);

        if($canuploadfiles) {
            $hasfiles = get_string('no');

            $files = bank_helper::files($bankentry->id);
            if ( count($files) > 0) {
                $hasfiles = get_string('yes');
            }
            array_push($dataarray, $hasfiles);
        }
        array_push($dataarray, $buttongo);
        $table->data[] = $dataarray;
    }
    echo html_writer::table($table);
}

echo $OUTPUT->footer();

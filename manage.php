<?php

use core_payment\helper;
use gwpayiments\bank_helper as GwpayimentsBank_helper;
use paygw_bank\bank_helper as Paygw_bankBank_helper;
use paygw_bank\bank_helper;

require_once __DIR__ . '/../../../config.php';
require_once './lib.php';
require_login();
$context = context_system::instance(); // Because we "have no scope".
$PAGE->set_context($context);
$systemcontext = \context_system::instance();
$PAGE->set_url('/payment/gateway/bank/manage.php');
$PAGE->set_pagelayout('report');
$pagetitle = get_string('manage', 'paygw_bank');
$PAGE->set_title($pagetitle);
$PAGE->set_heading($pagetitle);
$PAGE->navbar->add(get_string('pluginname', 'paygw_bank'), $PAGE->url);
$confirm = optional_param('confirm', 0, PARAM_INT);
$id = optional_param('id', 0, PARAM_INT);
$action = optional_param('action', '', PARAM_TEXT);

echo $OUTPUT->header();

require_capability('paygw/bank:managepayments', $systemcontext);

echo $OUTPUT->heading(get_string('pending_payments', 'paygw_bank'), 2);
if ($confirm == 1 && $id > 0) {
    require_sesskey();
    if ($action == 'A') {
        bank_helper::aprobe_pay($id);
        $OUTPUT->notification("aprobed");
        \core\notification::info("aprobed");
    }
    if ($action == 'D') {
        bank_helper::deny_pay($id);
        \core\notification::info("denied");
        $OUTPUT->notification("denied");
    }
}
$posturl = new moodle_url($PAGE->url, array('sesskey' => sesskey()));
$bankentries = bank_helper::get_pending();
if (!$bankentries) {
    $match = array();
    echo $OUTPUT->heading(get_string('noentriesfound', 'paygw_bank'));

    $table = null;
} else {
    $table = new html_table();
    $table->head = array(
        get_string('date'), get_string('code', 'paygw_bank'), get_string('username'),  get_string('email'),
        get_string('concept', 'paygw_bank'), get_string('total_cost', 'paygw_bank'), get_string('currency'), get_string('hasfiles', 'paygw_bank'), get_string('actions')
    );
    // $headarray=array(get_string('date'),get_string('code', 'paygw_bank'), get_string('concept', 'paygw_bank'),get_string('amount', 'paygw_bank'),get_string('currency'));

    foreach ($bankentries as $bankentry) {
        $config = (object) helper::get_gateway_configuration($bankentry->component, $bankentry->paymentarea, $bankentry->itemid, 'bank');
        $payable = helper::get_payable($bankentry->component, $bankentry->paymentarea, $bankentry->itemid);
        $currency = $payable->get_currency();
        $customer = $DB->get_record('user', array('id' => $bankentry->userid));
        $fullname = fullname($customer, true);

        // Add surcharge if there is any.
        $surcharge = helper::get_gateway_surcharge('paypal');
        $amount = helper::get_rounded_cost($payable->get_amount(), $currency, $surcharge);
        $buttonaprobe = '<form name="formapprovepay' . $bankentry->id . '" method="POST">
        <input type="hidden" name="sesskey" value="' .sesskey(). '">
        <input type="hidden" name="id" value="' . $bankentry->id . '">
        <input type="hidden" name="action" value="A">
        <input type="hidden" name="confirm" value="1">
        <input class="btn btn-primary form-submit" type="submit" value="' . get_string('approve', 'paygw_bank') . '"></input>
        </form>';
        $buttondeny = '<form name="formaprovepay' . $bankentry->id . '" method="POST">
        <input type="hidden" name="sesskey" value="' .sesskey(). '">
        <input type="hidden" name="id" value="' . $bankentry->id . '">
        <input type="hidden" name="action" value="D">
        <input type="hidden" name="confirm" value="1">
        <input class="btn btn-primary form-submit" type="submit" value="' . get_string('deny', 'paygw_bank') . '"></input>
        </form>';
        $files = "-";
        $hasfiles = get_string('no');
        $fs = get_file_storage();
        $files = bank_helper::files($bankentry->id);
        if ($bankentry->hasfiles > 0 || count($files) > 0) {
            $hasfiles = get_string('yes');
            $hasfiles = '<button type="button" class="btn btn-primary" data-toggle="modal" data-target="#staticBackdrop' . $bankentry->id . '" id="launchmodal' . $bankentry->id . '">
            View
          </button>
          <!-- Modal -->
            <div class="modal fade" id="staticBackdrop' . $bankentry->id . '" aria-labelledby="staticBackdropLabel' . $bankentry->id . '" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="staticBackdropLabel' . $bankentry->id . '">' . get_string('files') . '</h5>
                    <button type="button" class="btn-close" data-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
              ';
            foreach ($files as $f) {
                // $f is an instance of stored_file
                $url = moodle_url::make_pluginfile_url($f->get_contextid(), $f->get_component(), $f->get_filearea(), $f->get_itemid(), $f->get_filepath(), $f->get_filename(), false);
                if (str_ends_with($f->get_filename(), ".png") || str_ends_with($f->get_filename(), ".jpg") || str_ends_with($f->get_filename(), ".gif")) {
                    $hasfiles .= "<img src='$url'><br>";
                } else {
                    $hasfiles .= '<a href="' . $url . '" target="_blank">.....' . $f->get_filename() . '</a><br>';
                }
            }
            $hasfiles .= '
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                </div>
                </div>
            </div>
            </div>
            ';
        }




        $table->data[] = array(
            date('Y-m-d', $bankentry->timecreated), $bankentry->code, $fullname, $customer->email, $bankentry->description,
            $amount, $currency, $hasfiles, $buttonaprobe . $buttondeny
        );
    }
    echo html_writer::table($table);
}

echo $OUTPUT->footer();

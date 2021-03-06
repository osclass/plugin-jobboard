<?php if ( !defined('ABS_PATH') ) exit('ABS_PATH is not loaded. Direct access is not allowed.');

class JobboardAjax
{
    public function __construct()
    {
        osc_add_hook('ajax_admin_jobboard_rating', array(&$this, 'ajax_rating_request'));
        osc_add_hook('ajax_admin_jobboard_answer_punctuation', array(&$this, 'ajax_answer_punctuation'));
        osc_add_hook('ajax_admin_applicant_status', array(&$this, 'ajax_applicant_status'));
        osc_add_hook('ajax_admin_applicant_status_message', array(&$this, 'ajax_applicant_status_message'));
        osc_add_hook('ajax_admin_applicant_status_notification', array(&$this, 'ajax_applicant_status_notification'));
        osc_add_hook('ajax_admin_applicant_save_notification', array(&$this, 'ajax_applicant_save_notification'));
        osc_add_hook('ajax_admin_note_add', array(&$this, 'ajax_note_add'));
        osc_add_hook('ajax_admin_note_edit', array(&$this, 'ajax_note_edit'));
        osc_add_hook('ajax_admin_note_delete', array(&$this, 'ajax_note_delete'));
        osc_add_hook('ajax_admin_question_delete', array(&$this, 'ajax_question_delete'));
        osc_add_hook('ajax_admin_dashboard_tour', array(&$this, 'ajax_dashboard_tour'));
    }

    /**
     * Set applicant rating, logging the action
     */
    function ajax_rating_request() {
        $result = ModelJB::newInstance()->setRating(Params::getParam('applicantId'), Params::getParam('rating'));
        if( ($result !== false) && ($result > 0) ) {
            // log rate an applicant
            $st = new Stream();
            $st->log_rate_applicant(Params::getParam('applicantId'), Params::getParam('rating'));
        }
    }

    /**
     * Set answer punctuation recalculating the puntuation of test (set of questions)
     */
    function ajax_answer_punctuation() {
        // update punctuation of << open questions >>
        $result = ModelKQ::newInstance()->updatePunctuationQuestionResult(Params::getParam('killerFormId'), Params::getParam('applicantId'), Params::getParam('questionId'), Params::getParam('punctuation'));
        if($result !== false) {
            $aInfo = ModelKQ::newInstance()->calculatePunctuationOfApplicant(Params::getParam('applicantId'));
            $score          = (float)number_format($aInfo['score'],1);
            echo json_encode( array('punctuation'   => Params::getParam('punctuation'),
                                     'score'       => $score,
                                     'corrected'   => $aInfo['corrected']) );
        } else {
            echo '0';
        }
    }

    /**
     * Change applicant statusm logging the action
     */
    function ajax_applicant_status() {
        $result = ModelJB::newInstance()->changeStatus(Params::getParam("applicantId"), Params::getParam("status"));
        if( ($result !== false) && ($result > 0 ) ) {
            $st = new Stream();
            $st->log_change_status_application(Params::getParam('applicantId'), Params::getParam('status'));
        }
    }

    /**
     * Send email applicant to inform about a change of candidature status
     * @return type
     */
    function ajax_applicant_status_message() {
        $applicantID = Params::getParam('applicantID');
        $status      = Params::getParam('status');

        $aStatus    = jobboard_status();
        $aApplicant = ModelJB::newInstance()->getApplicant($applicantID);

        if( count($aApplicant) === 0 ) {
            $json = array('error' => true);
            echo json_encode($json);
            return false;
        }

        $email_txt = array(
            'company_url'      => osc_base_url(),
            'company_link'     => sprintf('<a href="%1$s">%2$s</a>', osc_base_url(), osc_page_title()),
            'company_name'     => osc_page_title(),
            'admin_login_url'  => osc_admin_base_url(),
            'applicant_name'   => $aApplicant['s_name'],
            'applicant_status' => $aStatus[$status] //$aApplicant['i_status']
        );

        if( !is_null($aApplicant['fk_i_item_id']) ) {
            $aItem = Item::newInstance()->findByPrimaryKey($aApplicant['fk_i_item_id']);
            View::newInstance()->_exportVariableToView('item', $aItem);

            $email_txt['job_offer_title'] = osc_item_title();
            $email_txt['job_offer_link']  = sprintf('<a href="%1$s">%2$s</a>', osc_item_url(), osc_item_title());
            $email_txt['job_offer_url']   = osc_item_url();
        } else {
            $email_txt['job_offer_title'] = __('spontaneous', 'jobboard');
            $email_txt['job_offer_link']  = sprintf('<a href="%1$s">%2$s</a>', osc_contact_url(), __('spontaneous', 'jobboard'));
            $email_txt['job_offer_url']   = osc_contact_url();
        }

        // prepare email subject
        $email_subject = sprintf(__('Application status change at %1$s', 'jobboard'), osc_page_title());

        $email_body = sprintf(__('Hi %1$s,

        The %2$s company would like to inform you that your application for %3$s has changed to: %4$s.

        This is just an automatic message, to check the status of your application go to %5$s.

        Thanks and good luck!,
        %6$s','jobboard'),$email_txt['applicant_name'], $email_txt['company_name'], $email_txt['job_offer_link'], $email_txt['applicant_status'], $email_txt['company_link'], $email_txt['company_link']);

        $json = array('subject' => nl2br($email_subject), 'message' => nl2br($email_body), 'status' => $status, 'error' => false);
        echo json_encode($json);
        return true;
    }

    /**
     * applicant to inform about a change of candidature status
     *
     * @return type
     */
    function ajax_applicant_status_notification() {
        $applicantID = Params::getParam('applicantID');
        $message     = Params::getParam('message', false, false);
        $subject     = Params::getParam('subject', false, false);

        if( $message === '' ) {
            echo 'false';
            return false;
        }

        // check if the applicant exist
        $aApplicant = ModelJB::newInstance()->getApplicant($applicantID);
        if( count($aApplicant) === 0 ) {
            echo 'false';
            return false;
        }

        // prepare email params
        $params = array(
            'to'       => $aApplicant['s_email'],
            'to_name'  => $aApplicant['s_name'],
            'reply_to' => osc_contact_email(),
            'subject'  => $subject,
            'body'     => $message
        );
        // send email
        osc_sendMail($params);
        echo 'true';
        return true;
    }

    /**
     * applicant save an email
     *
     * @return type
     */
    function ajax_applicant_save_notification() {
        $applicantID = Params::getParam('applicantID');
        $message     = Params::getParam('message', false, false);
        $subject     = Params::getParam('subject', false, false);

        if( $message === '' ) {
            return NULL;
        }

        // check if the applicant exist
        $aApplicant = ModelJB::newInstance()->getApplicant($applicantID);
        if( count($aApplicant) === 0 ) {
            return NULL;
        }

        // save email format json
        applicant_emailsent_insert($applicantID, $subject, $message);

        return true;
    }

    /**
     * Save applicant note, logging the action
     */
    function ajax_note_add() {
        $noteID = ModelJB::newInstance()->insertNote(Params::getParam('applicantID'), Params::getParam('noteText'));
        if( ($noteID !== false) && ($noteID > 0 ) ) {
            $st = new Stream();
            $st->log_new_note(Params::getParam('applicantID'));
        }
        $aNote = ModelJB::newInstance()->getNoteByID($noteID);
        $aNote["admin_username"] = ModelJB::newInstance()->getAdminUsername($aNote["fk_i_admin_id"]);
        $aNote['day']            = date('d', strtotime($aNote['dt_date']));
        $aNote['month']          = date('M', strtotime($aNote['dt_date']));
        $aNote['year']           = date('Y', strtotime($aNote['dt_date']));
        echo json_encode($aNote);
    }

    /**
     * Update applicant note, logging the action
     */
    function ajax_note_edit() {
        $result = ModelJB::newInstance()->updateNote(Params::getParam('noteID'), Params::getParam('noteText'));
        if( ($result !== false) && ($result > 0 ) ) {
            $st = new Stream();
            $st->log_edit_note(Params::getParam('applicantID'), Params::getParam('noteID'));
        }
        $aNote = ModelJB::newInstance()->getNoteByID(Params::getParam('noteID'));
        $aNote["admin_username"] = ModelJB::newInstance()->getAdminUsername($aNote["fk_i_admin_id"]);
        $aNote['day']            = date('d', strtotime($aNote['dt_date']));
        $aNote['month']          = date('M', strtotime($aNote['dt_date']));
        $aNote['year']           = date('Y', strtotime($aNote['dt_date']));

        echo json_encode($aNote);
    }

    /**
     * Remove applicant note, logging the action
     */
    function ajax_note_delete() {
        $note   = ModelJB::newInstance()->getNoteByID(Params::getParam('noteID'));
        $result = ModelJB::newInstance()->deleteNote(Params::getParam('noteID'));
        if( ($result !== false) && ($result > 0 ) ) {
            $st = new Stream();
            $st->log_remove_note(Params::getParam('applicantID'), Params::getParam('noteID'));
        }
    }

    /**
     * Remove 'killer' question
     */
    function ajax_question_delete() {
        $result = ModelKQ::newInstance()->removeKillerQuestion(Params::getParam('killerFormId'), Params::getParam('questionId'));

        if( ($result !== false) && ($result > 0) ) {
            echo 1;
        } else {
            echo 0;
        }
    }

    function ajax_dashboard_tour()
    {
        osc_set_preference('dashboard_tour_visible', false, 'jobboard_plugin');
        echo json_encode(array('status' => true));
    }
}

$ja = new JobboardAjax();

// EOF
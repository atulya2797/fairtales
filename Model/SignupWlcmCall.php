<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class SignupWlcmCall extends Model {

    /*Max unverify attempts*/
    const MaxCountNotVerify = 5;

    /*Type of outcome*/
    const Outcome_Other = 100;
    const Outcome_Ringing = 1;
    const Outcome_Callback = 2;
    const Outcome_Out_of_reach = 3;
    const Outcome_Wrong_Number = 4;
    const Outcome_Call_Disconnected = 5;
    const Outcome_Cancellation = 6;
    const Outcome_Emailed_For_Cancellation = 7;
    const Outcome_One_Time = 8;
    const Outcome_Verified = 9;
    const Outcome_Reject = 10;
    const Outcome_No_Answer = 11;
    const Outcome_Busy = 12;
    const Outcome_Call_scheduled_For_Later_Time = 13;
    const Outcome_Switched_Off = 14;
    const Outcome_Language_Barrier = 15;
    const Outcome_Invalid_Number = 16;
    const Outcome_Incoming_Call = 17;
    const Outcome_Financial_Issues = 18;
    const Outcome_Underage_Unverified = 19;
    const Outcome_Not_Interested_No_Support = 20;
    const Outcome_Not_Interested_One_Time_Support = 21;
    const Outcome_Unemployed_Supporter = 22;
    const Outcome_Donor_Wants_Debit_On_Specific_Date = 23;
    const Outcome_Supporter_Wants_Call_Before_Every_Debit = 24;
    const Outcome_Misrepresentation_Wrong_Info = 25;
    const Outcome_Misrepresentation = 26;
    const Outcome_Supporter_Do_Not_Want_Verification = 27;


    /**
     * SupLang
     */
    const SupLang_English = 1;
    const SupLang_Hingi = 2;
    const SupLang_Tamil = 3;
    const SupLang_Bangla = 4;
    const SupLang_Malayalam = 5;
    const SupLang_Kannada = 6;
    const SupLang_Local = 7;

    /**
     *
     * Call_FinalStatus
     */
    const Call_FinalStatus_verified = 1;
    const Call_FinalStatus_not_verified = 2;
    const Call_FinalStatus_rejected = 3;
    const Call_FinalStatus_process_unverified = 4;

    protected $table = 'Signup_WlcmCall';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'CRM_ID', 'Call_Attempt', 'Call_FinalStatus', 'Call_FinalStatusRemark', 'CallBackTime', 'SupLang', 'IsSupAwareCause', 'IsSupAwareMonthly', 'IsWillingAddrs', 'IsEnqCanx', 'IsSpotVer', 'tempCallSid', 'created_at', 'updated_at'
    ];

    public static function getCallOutcome() {
        return [
//            self::Outcome_Ringing => 'Ringing',
//            self::Outcome_Cancellation => 'Cancellation',
//            self::Outcome_Emailed_For_Cancellation => 'Emailed For Cancellation',
//            self::Outcome_One_Time => 'One Time',
//            self::Outcome_Reject => 'Reject',
            self::Outcome_Verified => 'Verified',
            self::Outcome_No_Answer => 'No Answer',
            self::Outcome_Busy => 'Busy',
            self::Outcome_Callback => 'Callback',
            self::Outcome_Call_scheduled_For_Later_Time => 'Call scheduled for later time',
            self::Outcome_Switched_Off => 'Switched Off',
            self::Outcome_Call_Disconnected => 'Call Disconnected',
            self::Outcome_Wrong_Number => 'Wrong Number',
            self::Outcome_Language_Barrier => 'Language Barrier: Reassigned to appropriate TM',
            self::Outcome_Invalid_Number => 'Invalid Number',
            self::Outcome_Out_of_reach => 'Out Of Reach',
            self::Outcome_Incoming_Call => 'Incoming Call',
            self::Outcome_Financial_Issues => 'Financial Issues',
            self::Outcome_Underage_Unverified => 'Underage Unverified',
            self::Outcome_Not_Interested_No_Support => 'Not Interested - Supporter Does Not Want To Support',
            self::Outcome_Not_Interested_One_Time_Support => 'Not Interested - Supporter Wants To Support Only One Time',
            self::Outcome_Unemployed_Supporter => 'Unemployed Supporter',
            self::Outcome_Donor_Wants_Debit_On_Specific_Date => 'Donor Wants Debit On Specific Date',
            self::Outcome_Supporter_Wants_Call_Before_Every_Debit => 'Supporter Wants Call Before Every Debit',
            self::Outcome_Misrepresentation_Wrong_Info => 'Misrepresentation - Wrong Information About Debit Date',
            self::Outcome_Misrepresentation => 'Misrepresentation - Supporter Wants Debit From Next Month',
            self::Outcome_Supporter_Do_Not_Want_Verification => 'Supporter Do Not Want Verification Call',
            self::Outcome_Other => 'Other',
        ];
    }

    public static function getCallFinalStatus() {
        return [
            self::Call_FinalStatus_verified => 'Verified',
            self::Call_FinalStatus_not_verified => 'Not Verified',
            self::Call_FinalStatus_rejected => 'Rejected',
            self::Call_FinalStatus_process_unverified => 'Process Unverified'
        ];
    }

    public function getSignup() {
        return $this->hasOne('App\Model\Signup', 'CRM_ID', 'CRM_ID');
    }

    public function getSignupWlcmCallDetail() {
        return $this->hasMany('App\Model\WlcmCallDetail', 'CRM_ID', 'CRM_ID');
    }

}

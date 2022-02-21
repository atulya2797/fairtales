<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class SignupFormChk extends Model {

    protected $table = 'Signup_FormChk';

    const FFPStatus_Accept = 1;
    const FFPStatus_Reject = 2;
    const FFPStatus_Modify = 3;

    /**
     *
     */
    const BCopySubAvailable = 1;
    const BCopySubNotAvailable = 0;

    /**
     * Text
     */
    const FFPStatus_Accept_Text = 'Accept';
    const FFPStatus_Reject_Text = 'Reject';
    const FFPStatus_Modify_Text = 'Modify';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'CRM_ID', 'IsOverwrite', 'BCopySub', 'IsActionTypeTick', 'IsAccntTypeTick', 'IsAccntHldrNameMention', 'IsBankNameMention', 'IsDebitTypeTick', 'IsPhoneEmailMentionNACH', 'IsAmountWordFigCheck', 'IsStartDateMention', 'IsPostDated', 'NoOfSignACopy', 'NoOfSignBCopy', 'IsAddresComplete', 'IsPinCodeCap', 'IsPhoneMention', 'IsEmailMention', 'IsDOBMention', 'SupFormAckSign', 'IsFidMention', 'FFPStatus', 'Remarks', 'created_at', 'updated_at'
    ];

    public function getSignup() {
        return $this->hasOne('App\Model\Signup', 'CRM_ID', 'CRM_ID');
    }

    public static function getFFPStatus() {
        return [
            self::FFPStatus_Accept => 'Accept',
            self::FFPStatus_Reject => 'Reject',
            self::FFPStatus_Modify => 'Modify'
        ];
    }

}

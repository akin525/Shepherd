<?php

namespace App\Http\Controllers;

use App\Models\SiteAssessment;
use Illuminate\Http\Request;

class AssessmentController
{
    function index(Request $request)
    {

        $user = $request->user();
        $employee=$user->employee;
        $clientStaff=$employee->clientStaff;

        $Assessments['total']=SiteAssessment::where('client_id',$clientStaff->id)->count();
        $Assessments['complete']=SiteAssessment::where('status','submitted')->count();

    }

}

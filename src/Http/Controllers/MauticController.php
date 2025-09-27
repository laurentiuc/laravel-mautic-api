<?php

namespace Triibo\Mautic\Http\Controllers;

use Illuminate\Routing\Controller;
use Triibo\Mautic\Facades\Mautic;
use Triibo\Mautic\Models\MauticConsumer;

class MauticController extends Controller
{
    /**
     * Setup Applicaion.
     *
     * @return  void
     */
    public function initiateApplication()
    {
        $message = "<h1>Mautic App Already Register</h1>";

        if (MauticConsumer::count() == 0) {
            Mautic::connection("main");
            $message = "<h1>Mautic App Successfully Registered</h1>";
        }

        echo $message;
    }
}

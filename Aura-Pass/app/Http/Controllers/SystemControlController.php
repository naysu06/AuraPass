<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class SystemControlController extends Controller
{
    public function stop()
    {
        $scriptPath = base_path('AuraPass-Stop.bat');

        // THE FIX: pclose(popen(...)) completely detaches the Windows process.
        // PHP will trigger the bat file and instantly move to the next line without waiting.
        pclose(popen("start /B cmd /c \"{$scriptPath}\" > NUL 2>&1", "r"));

        // Because PHP didn't wait, it instantly sends this HTML to the browser 
        // while the batch file does its 2-second countdown in the background.
        return response("
            <!DOCTYPE html>
            <html lang='en'>
            <head>
                <meta charset='UTF-8'>
                <meta name='viewport' content='width=device-width, initial-scale=1.0'>
                <title>AuraPass Offline</title>
                <style>
                    body {
                        background-color: #0f172a;
                        color: #f8fafc;
                        font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        height: 100vh;
                        margin: 0;
                        text-align: center;
                    }
                    .shutdown-card {
                        background-color: #1e293b;
                        padding: 2.5rem 2rem;
                        border-radius: 0.75rem;
                        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.5);
                        border: 1px solid #334155;
                        max-width: 28rem;
                        width: 90%;
                    }
                    .success-icon {
                        color: #10b981;
                        width: 4rem;
                        height: 4rem;
                        margin: 0 auto 1rem auto;
                    }
                    h1 {
                        font-size: 1.5rem;
                        font-weight: 600;
                        margin-bottom: 0.5rem;
                        color: #f1f5f9;
                    }
                    p {
                        color: #94a3b8;
                        line-height: 1.6;
                        margin-bottom: 1.5rem;
                    }
                    .safe-close {
                        display: inline-block;
                        padding: 0.5rem 1rem;
                        background-color: #334155;
                        color: #cbd5e1;
                        border-radius: 0.375rem;
                        font-size: 0.875rem;
                        font-weight: 500;
                    }
                </style>
            </head>
            <body>
                <div class='shutdown-card'>
                    <svg class='success-icon' fill='none' stroke='currentColor' viewBox='0 0 24 24' xmlns='http://www.w3.org/2000/svg'>
                        <path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'></path>
                    </svg>
                    <h1>System Offline</h1>
                    <p>AuraPass has been successfully shut down. All background services and databases have been safely terminated.</p>
                    <div class='safe-close'>
                        You may now close this tab safely.
                    </div>
                </div>
            </body>
            </html>
        ");
    }
}
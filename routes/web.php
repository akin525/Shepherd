<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/fix-storage', function () {
    $targetFolder = storage_path('app/public');
    $linkFolder = $_SERVER['DOCUMENT_ROOT'] . '/storage';

    try {
        symlink($targetFolder, $linkFolder);
        return 'Success! The storage link has been created for cPanel.';
    } catch (\Exception $e) {
        return 'Error: ' . $e->getMessage();
    }
});

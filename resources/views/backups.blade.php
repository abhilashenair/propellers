@extends('layout')
@section('title','Backups')
@section('content')
<div class="page-head"><div><h1>Backups</h1><p class="subtext">Download your data or restore an earlier backup.</p></div></div>
<div class="panel"><div class="panel-head"><h2>Download data backup</h2></div><div class="panel-body"><p>Includes customers, vendors, bills, movement updates, staff accounts, booking options, and settings. Protect the file with a backup password to restore it on another installation, including local-to-online transfers. Keep this password safe; it cannot be recovered.</p><p>Uploaded logos and photos are not included. Keep a separate copy of uploaded files and your server configuration, or use Lightsail snapshots for a complete server backup.</p><form method="post" action="{{ route('backups.download') }}">@csrf<label class="field">Choose a backup password<input type="password" name="backup_password" minlength="12" maxlength="128" autocomplete="new-password" required><small>At least 12 characters. This password protects the backup file.</small></label><label class="field">Confirm backup password<input type="password" name="backup_password_confirmation" minlength="12" maxlength="128" autocomplete="new-password" required></label><button class="button">Download data backup</button></form></div></div>
<form method="post" enctype="multipart/form-data" action="{{ route('backups.restore') }}">@csrf
<div class="panel"><div class="panel-head"><h2>Upload and restore data</h2></div><div class="panel-body">
<p><strong>Restoring replaces all current business records, accounts, and settings.</strong> Everyone will be signed out. Use the login details saved in the restored backup. A recovery copy of the current data is saved privately on the server before replacement.</p>
<label class="field">Data backup file<input type="file" name="backup" accept=".backup" required><small>Select a .backup file downloaded above, up to 10 MB. SQL downloads cannot be uploaded here.</small></label>
<label class="field">Backup password<input type="password" name="backup_password" maxlength="128" autocomplete="off"><small>Enter the password chosen when downloading. Leave blank only for older backups from this same installation. For a local-to-online transfer, first download a new backup using a password on the local installation.</small></label>
<label class="field">Your current admin password<input type="password" name="password" autocomplete="current-password" required></label>
<label><input type="checkbox" name="confirm_restore" value="1" required> I understand that this will replace the current data.</label>
</div></div><div class="form-actions"><button class="button">Restore backup</button></div></form>
@endsection

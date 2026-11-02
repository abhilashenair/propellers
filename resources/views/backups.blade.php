@extends('layout')
@section('title','Backups')
@section('content')
<div class="page-head"><div><h1>Backups</h1><p class="subtext">Download your data or restore an earlier backup.</p></div></div>
<div class="panel"><div class="panel-head"><h2>Download data backup</h2></div><div class="panel-body"><p>Includes customers, vendors, bills, movement updates, staff accounts, booking options, and settings. The file is encrypted and can only be restored with this installation’s original application key.</p><p>Uploaded logos and photos are not included. Keep a separate copy of uploaded files and your server configuration, or use Lightsail snapshots for a complete server backup.</p><form method="post" action="{{ route('backups.download') }}">@csrf<button class="button">Download data backup</button></form></div></div>
<form method="post" enctype="multipart/form-data" action="{{ route('backups.restore') }}">@csrf
<div class="panel"><div class="panel-head"><h2>Upload and restore data</h2></div><div class="panel-body">
<p><strong>Restoring replaces all current business records, accounts, and settings.</strong> Everyone will be signed out. Use the login details saved in the restored backup. A recovery copy of the current data is saved privately on the server before replacement.</p>
<label class="field">Data backup file<input type="file" name="backup" accept=".backup" required><small>Select a .backup file downloaded above, up to 10 MB. SQL downloads cannot be uploaded here.</small></label>
<label class="field">Your current admin password<input type="password" name="password" autocomplete="current-password" required></label>
<label><input type="checkbox" name="confirm_restore" value="1" required> I understand that this will replace the current data.</label>
</div></div><div class="form-actions"><button class="button">Restore backup</button></div></form>
@endsection

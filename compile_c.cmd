php "%__CD__%make_xhtmls.php" c
powershell "%__CD__%compile_c_zip.ps1"
makecab.exe %__CD__%creference.mshc %__CD__%creference.cab
DEL %__CD__%creference.mshc
signtool.exe sign /a /r verisign /fd sha1 /t http://timestamp.verisign.com/scripts/timstamp.dll /v %__CD__%creference.cab
signtool.exe sign /a /r verisign /as /fd sha256 /tr http://sha256timestamp.ws.symantec.com/sha256/timestamp /v %__CD__%creference.cab
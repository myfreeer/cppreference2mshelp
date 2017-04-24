php "%__CD__%make_xhtmls.php" cpp
powershell "%__CD__%compile_cpp_zip.ps1"
makecab.exe %__CD__%cppreference.mshc %__CD__%cppreference.cab
DEL %__CD__%cppreference.mshc
signtool.exe sign /a /r verisign /fd sha1 /t http://timestamp.verisign.com/scripts/timstamp.dll /v %__CD__%cppreference.cab
signtool.exe sign /a /r verisign /as /fd sha256 /tr http://sha256timestamp.ws.symantec.com/sha256/timestamp /v %__CD__%cppreference.cab
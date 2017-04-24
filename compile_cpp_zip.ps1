$pathfolder = Split-Path -Path $MyInvocation.MyCommand.Path
$pathmshc = Join-Path $pathfolder '\cppreference.mshc'
$pathcab = Join-Path $pathfolder '\cppreference.cab'
$pathfolder = Join-Path $pathfolder '\cpphelp'
Remove-Item $pathmshc
Remove-Item $pathcab
Add-Type -A System.IO.Compression.FileSystem
[IO.Compression.ZipFile]::CreateFromDirectory( $pathfolder , $pathmshc )
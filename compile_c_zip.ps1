$pathfolder = Split-Path -Path $MyInvocation.MyCommand.Path
$pathmshc = Join-Path $pathfolder '\creference.mshc'
$pathcab = Join-Path $pathfolder '\creference.cab'
$pathfolder = Join-Path $pathfolder '\chelp'
Remove-Item $pathmshc
Remove-Item $pathcab
Add-Type -A System.IO.Compression.FileSystem
[IO.Compression.ZipFile]::CreateFromDirectory( $pathfolder , $pathmshc )

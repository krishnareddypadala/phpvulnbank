sourceanalyzer -b "vulnb" -clean
sourceanalyzer -b "vulnb"  /**/*
sourceanalyzer -b "vulnb"  --show-files
sourceanalyzer -b "vulnb"  -scan > vuln.txt

$cr=(Select-String -Path .\vuln.txt -Pattern "Critical").length
$hi=(Select-String -Path .\vuln.txt -Pattern "High").length
$me=(Select-String -Path .\vuln.txt -Pattern "Medium").length
$lo=(Select-String -Path .\vuln.txt -Pattern "Low").length


Write-Host "Critical Vulnerabilities:"$cr

Write-Host "High Vulnerabilities:"$hi

Write-Host "Medium Vulnerabilities:"$me

Write-Host "Low Vulnerabilities:"$lo

if($cr -gt 0)
{

  Write-Host "Dude You are the worst developer I ever come across and fix bugs"
  exit 1

}

else
 
{

  Write-Host "Dude You are the AWSome developer I ever come across"

 
}

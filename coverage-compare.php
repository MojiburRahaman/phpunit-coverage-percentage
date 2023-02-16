<?php
declare(strict_types=1);
const XPATH_METRICS = '//metrics';
const STATUS_OK = 0;
const STATUS_ERROR = 1;
const TOTAL = 100;
/**
 * @throws Exception
 */
function loadMetrics(string $file): array
{
    $xml = new SimpleXMLElement(file_get_contents($file));
    return $xml->xpath(XPATH_METRICS);
}
/**
 * @param string $msg
 * @param int $exitCode
 * @return void
 */
function printStatus(string $msg, int $exitCode = STATUS_OK): void
{
    echo $msg . PHP_EOL;
    exit($exitCode);
}
/**
 * @param string $file
 * @return bool
 */
function isXmlFile(string $file): bool
{
    $ext = pathinfo($file, PATHINFO_EXTENSION);
    return $ext === 'xml';
}
/**
 * @param string $file
 * @return string
 * @throws Exception
 */
function calculate(string $file): string
{
    $elements = 0;
    $coveredElements = 0;
    $statements = 0;
    $coveredstatements = 0;
    $methods = 0;
    $coveredmethods = 0;
    foreach (loadMetrics($file) as $metric) {
        $elements += (int)$metric['elements'];
        $coveredElements += (int)$metric['coveredelements'];
        $statements += (int)$metric['statements'];
        $coveredstatements += (int)$metric['coveredstatements'];
        $methods += (int)$metric['methods'];
        $coveredmethods += (int)$metric['coveredmethods'];
    }
    // See calculation: https://confluence.atlassian.com/pages/viewpage.action?pageId=79986990
    $coveredMetrics = $coveredstatements + $coveredmethods + $coveredElements;
    $totalMetrics = $statements + $methods + $elements;
    if ($totalMetrics === 0) {
        printStatus('Insufficient data for calculation. Please add more code.', STATUS_ERROR);
    }
    $totalPercentageCoverage = $coveredMetrics / $totalMetrics * 100;
    return number_format($totalPercentageCoverage, 3);
}
if (!isset($argv[1]) || !isset($argv[2])) {
    printStatus("Expected 2 argument, got " . (count($argv) - 1) . ".", STATUS_ERROR);
}
if (count($argv) > 3) {
    printStatus("Too many arguments provided. Expected 2, got " . (count($argv) - 1) . ".", STATUS_ERROR);
}
$inputFileOne = $argv[1];
$inputFileTwo = $argv[2];
foreach ([$inputFileOne, $inputFileTwo] as $inputFile) {
    if (!file_exists($inputFile)) {
        printStatus("Invalid input file '$inputFile' provided. The file was not found.", STATUS_ERROR);
    }
    if (!isXmlFile($inputFile)) {
        printStatus("Invalid input file '$inputFile' provided. The file must be in XML format.", STATUS_ERROR);
    }
}
try {
    $inputFileOneCoverage = calculate($inputFileOne);
    $inputFileTwoCoverage = calculate($inputFileTwo);
} catch (Exception $e) {
    printStatus("Failed to generate code coverage.", STATUS_ERROR);
}

$data = [
    ['Total', 'Previous', 'Current'],
    [TOTAL.'%', $inputFileTwoCoverage.'%', $inputFileOneCoverage.'%']
];

foreach ($data as $value)
{
//    print table row
    printf("+-%'-8s-+-%'-8s-+-%'-8s-+\n", '', '', '');
//    print table value
    printf("| %' 8s | %' 8s | %' 8s |\n", ...$value);

}
//print table footer
printf("+-%'-8s-+-%'-8s-+-%'-8s-+\n", '', '', '');

if ($inputFileOneCoverage < $inputFileTwoCoverage) {
    printStatus(
        'Total code coverage is ' . $inputFileOneCoverage . '% which is below the accepted code coverage ' .
        $inputFileTwoCoverage . '% , Please write more tests.',STATUS_ERROR
    );
}
printStatus('Total code coverage is ' . $inputFileOneCoverage . '% – OK!');

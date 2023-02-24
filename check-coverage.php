<?php
declare(strict_types=1);
const STATUS_OK = 0;
const STATUS_ERROR = 1;
const MINIMUM_REQUIRED_PERCENTAGE = 75;
const COVERAGE_REPORT_FILE_PATH = './coverage/coverage-report.json';
const COVERAGE_SUMMARY_FILE_PATH = './coverage/coverage-summary.xml';

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

function calculateCoverageDifference(float $oldCoverage, float $newCoverage): string
{
    if ($newCoverage >= $oldCoverage) {
        return number_format((100 * $newCoverage) / $oldCoverage, 2);
    }
    return number_format(($newCoverage * 2) - $oldCoverage, 2);
}

/**
 * @param $xmlFile
 * @return array
 */
function calculate($xmlFile): array
{
    $file = $xmlFile->project->metrics->attributes();
    $coveredClasses = 0;
    foreach ($xmlFile->project->file as $class) {
        if (!empty($class->class->metrics['methods'])) {
            $coveredClasses += (int)$class->class->metrics['coveredmethods'] == (int)$class->class->metrics['methods'] ?
                1 : 0;
        }
    }
    $totalClasses = $file['classes'];
    $classCoverage = $totalClasses > 0 ? $coveredClasses / $totalClasses * 100 : 100;
    $totalMethods = $file['methods'];
    $coveredMethods = $file['coveredmethods'];
    $methodCoverage = $totalMethods > 0 ? $coveredMethods / $totalMethods * 100 : 100;
    $totalLines = $file['statements'];
    $coveredLines = $file['coveredstatements'];
    $lineCoverage = $totalLines > 0 ? $coveredLines / $totalLines * 100 : 100;
    return [
        'Classes' => number_format($classCoverage, 2),
        'Methods' => number_format($methodCoverage, 2),
        'Lines' => number_format($lineCoverage, 2)
    ];
}

if (!file_exists(COVERAGE_REPORT_FILE_PATH)) {
    printStatus("Coverage report not found", STATUS_ERROR);
}

$inputFile = COVERAGE_SUMMARY_FILE_PATH;
$lastCoverageReport = file_get_contents(COVERAGE_REPORT_FILE_PATH);
$lastCoverageReport = json_decode($lastCoverageReport, true)['lastCoverageReport'];

if (!file_exists($inputFile)) {
    printStatus("Invalid input file '$inputFile' provided. The file was not found.", STATUS_ERROR);
}
if (!isXmlFile($inputFile)) {
    printStatus("Invalid input file '$inputFile' provided. The file must be in XML format.", STATUS_ERROR);
}

$xml = simplexml_load_file($inputFile);

try {
    $currentCoverage = calculate($xml);
} catch (Exception $e) {
    printStatus("Failed to calculate current code coverage.", STATUS_ERROR);
}
$currentPRCoverage = [
    'Classes' => calculateCoverageDifference((float)$lastCoverageReport['Classes'], (float)$currentCoverage['Classes']),
    'Methods' => calculateCoverageDifference((float)$lastCoverageReport['Methods'], (float)$currentCoverage['Methods']),
    'Lines' => calculateCoverageDifference((float)$lastCoverageReport['Lines'], (float)$currentCoverage['Lines'])
];
$data = [
    ['Index', 'Previous', 'Current PR', 'Current'],
    ['Classes', "{$lastCoverageReport['Classes']}%", "{$currentPRCoverage['Classes']}%", "{$currentCoverage['Classes']}%"],
    ['Methods', "{$lastCoverageReport['Methods']}%", "{$currentPRCoverage['Methods']}%", "{$currentCoverage['Methods']}%"],
    ['Lines', "{$lastCoverageReport['Lines']}%", "{$currentPRCoverage['Lines']}%", "{$currentCoverage['Lines']}%"]
];
$columnWidths = [10, 10, 10, 10];
echo "\n";
// Print the data in a tabular format
for ($i = 0; $i < array_sum($columnWidths) + 2 * count($columnWidths) - 1; $i++) {
    echo "-";
}
echo "\n";
foreach ($data as $row) {
    for ($i = 0; $i < count($row); $i++) {
        printf("| %-${columnWidths[$i]}s", $row[$i]);
    }
    echo "\n";
// Print a horizontal line after each row
    for ($i = 0; $i < array_sum($columnWidths) + 2 * count($columnWidths) - 1; $i++) {
        echo "-";
    }
    echo "\n";
}
if (
    $currentPRCoverage['Classes'] < MINIMUM_REQUIRED_PERCENTAGE ||
    $currentPRCoverage['Methods'] < MINIMUM_REQUIRED_PERCENTAGE ||
    $currentPRCoverage['Lines'] < MINIMUM_REQUIRED_PERCENTAGE
) {
    printStatus(
        'Coverage is less than before, Please write more tests.', STATUS_ERROR
    );
} else {
    $report = [
        'lastCoverageReport' => [
            'Classes' => $currentCoverage['Classes'],
            'Methods' => $currentCoverage['Methods'],
            'Lines' => $currentCoverage['Lines'],
            'Timestamp' => time(),
        ]
    ];
    file_put_contents(COVERAGE_REPORT_FILE_PATH, json_encode($report));
    printStatus('Coverage is equal or more than before, Last coverage report has been updated with the new one');
}

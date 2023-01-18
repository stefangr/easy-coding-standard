<?php

declare(strict_types=1);

namespace Symplify\EasyCodingStandard\Console\Output;

use Symplify\EasyCodingStandard\Console\Style\EasyCodingStandardStyle;
use Symplify\EasyCodingStandard\Contract\Console\Output\OutputFormatterInterface;
use Symplify\EasyCodingStandard\ValueObject\Configuration;
use Symplify\EasyCodingStandard\ValueObject\Error\ErrorAndDiffResult;

final class CheckstyleOutputFormatter implements OutputFormatterInterface
{
    /**
     * @var string
     */
    public const NAME = 'checkstyle';

    public function __construct(
        private readonly EasyCodingStandardStyle $easyCodingStandardStyle,
        private readonly ExitCodeResolver $exitCodeResolver
    ) {
    }

    public function report(ErrorAndDiffResult $errorAndDiffResult, Configuration $configuration): int
    {
        $xml = $this->createXmlContent($errorAndDiffResult);
        $this->easyCodingStandardStyle->writeln($xml);

        return $this->exitCodeResolver->resolve($errorAndDiffResult, $configuration);
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function createXmlContent(ErrorAndDiffResult $errorAndDiffResult): string
    {
        $errorsArray = [];
        $codingStandardErrors = $errorAndDiffResult->getErrors();
        foreach ($codingStandardErrors as $codingStandardError) {
            $errorsArray[$codingStandardError->getRelativeFilePath()]['errors'][] = [
                'line' => $codingStandardError->getLine(),
                'message' => $codingStandardError->getMessage(),
                'source_class' => $codingStandardError->getCheckerClass(),
            ];
        }

        $fileDiffs = $errorAndDiffResult->getFileDiffs();
        foreach ($fileDiffs as $fileDiff) {
            $errorsArray[$fileDiff->getRelativeFilePath()]['checkers'][] = $fileDiff->getAppliedCheckers();
        }

        ksort($errorsArray);

        $dom = new \DOMDocument('1.0', 'UTF-8');

        $checkStyle = $dom->appendChild($dom->createElement('checkstyle'));

        /**
         * @var array{checkers: ?list<list<string>>, errors: ?list<array{line: int, message: string, source_class: string}>} $pathErrors
         */
        foreach ($errorsArray as $path => $pathErrors) {
            $file = $dom->createElement('file');
            $file->setAttribute('name', $path);
            $checkStyle->appendChild($file);

            if (isset($pathErrors['checkers'])) {
                foreach (array_unique(array_merge(...$pathErrors['checkers'])) as $appliedChecker) {
                    $file->appendChild($this->createCheckerEntry($dom, $appliedChecker));
                }
            }

            if (isset($pathErrors['errors'])) {
                foreach ($pathErrors['errors'] as $errorData) {
                    $file->appendChild($this->createErrorEntry($dom, $errorData));
                }
            }
        }

        $dom->formatOutput = true;

        return (string) $dom->saveXML();
    }

    private function createCheckerEntry(\DOMDocument $dom, string $appliedChecker): \DOMElement
    {
        $error = $dom->createElement('error');
        $error->setAttribute('severity', 'warning');
        $error->setAttribute('source', 'ECS.' . $appliedChecker);
        $error->setAttribute('message', 'Found violation(s) of type ' . $appliedChecker);

        return $error;
    }

    /**
     * @param array{line: int, message: string, source_class: string} $errorData
     */
    private function createErrorEntry(\DOMDocument $dom, array $errorData): \DOMElement
    {
        $error = $dom->createElement('error');
        $error->setAttribute('line', (string) $errorData['line']);
        $error->setAttribute('severity', 'error');
        $error->setAttribute('source', 'ECS.' . $errorData['source_class']);
        $error->setAttribute('message', $errorData['message']);

        return $error;
    }
}

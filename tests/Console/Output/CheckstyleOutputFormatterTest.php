<?php

declare(strict_types=1);

namespace Symplify\EasyCodingStandard\Tests\Console\Output;

use PHP_CodeSniffer\Standards\Squiz\Sniffs\Arrays\ArrayBracketSpacingSniff;
use Symplify\CodingStandard\Fixer\LineLength\LineLengthFixer;
use Symplify\EasyCodingStandard\Console\Output\CheckstyleOutputFormatter;
use Symplify\EasyCodingStandard\FileSystem\StaticRelativeFilePathHelper;
use Symplify\EasyCodingStandard\Kernel\EasyCodingStandardKernel;
use Symplify\EasyCodingStandard\SniffRunner\ValueObject\Error\CodingStandardError;
use Symplify\EasyCodingStandard\ValueObject\Error\ErrorAndDiffResult;
use Symplify\EasyCodingStandard\ValueObject\Error\FileDiff;
use Symplify\PackageBuilder\Testing\AbstractKernelTestCase;

class CheckstyleOutputFormatterTest extends AbstractKernelTestCase
{
    private CheckstyleOutputFormatter $checkstyleOutputFormatter;

    protected function setUp(): void
    {
        $this->bootKernel(EasyCodingStandardKernel::class);

        $this->checkstyleOutputFormatter = $this->getService(CheckstyleOutputFormatter::class);
    }

    public function test(): void
    {
        $relativeFilePath = StaticRelativeFilePathHelper::resolveFromCwd(__DIR__ . '/Source/RandomFile.php');

        $codingStandardErrors = [];
        $codingStandardErrors[] = new CodingStandardError(
            5,
            'some message',
            ArrayBracketSpacingSniff::class,
            $relativeFilePath
        );

        $fileDiffs = [];
        $fileDiffs[] = new FileDiff($relativeFilePath, 'some diff', 'some diff', [LineLengthFixer::class]);

        $fileDiffs[] = new FileDiff(
            $relativeFilePath,
            'some other diff',
            'some other diff',
            [LineLengthFixer::class]
        );

        $errorAndDiffResult = new ErrorAndDiffResult($codingStandardErrors, $fileDiffs, []);

        $xmlContent = $this->checkstyleOutputFormatter->createXmlContent($errorAndDiffResult);
        $this->assertStringMatchesFormatFile(
            __DIR__ . '/Fixture/expected_checkstyle_output.xml',
            $xmlContent . PHP_EOL
        );
    }
}

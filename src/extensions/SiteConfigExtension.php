<?php

namespace Odorisioe\SilverStripe\RobotsTxt\Extensions;

use Psr\Log\LoggerInterface;
use RuntimeException;
use SilverStripe\Control\Director;
use SilverStripe\Core\Extension;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Core\Path;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\TextareaField;
use SilverStripe\Forms\DropdownField;
use Throwable;

/**
 * SiteConfig extension to manage the robots.txt file
 *
 * @author Emmanuel Odorisio <odorisioe@gmail.com>
 * @package silverstripe-robots-txt
 */

class SiteConfigExtension extends Extension
{

    private static $db = [
        'RobotsTxtStatus' => 'Boolean',
        'RobotsTxtContentLive' => 'Text',
        'RobotsTxtContentTest' => 'Text'
    ];

    private static $defaults = [
        'RobotsTxtStatus' => true,
    ];

    /**
     * Tag used for logging errors to Raygun
     */
    public const RAYGUN_TAG = 'RobotsDotTxt';

    /**
     * This is really only required for testing purposes, so we can mock the path.
     */
    protected ?string $defaultRobotsDotTxtPath;

    /**
     * Retrieve the path for the robots.txt file.
     * By default, it resides in the '/public' directory, but the path is configurable
     * to support testing scenarios. This allows us to mock the file path during unit tests,
     * preventing overwriting the actual robots.txt file.
     * Additionally, this approach ensures separation between the mock file and
     * the public directory, which is why the PUBLIC_PATH constant is not used.
     */
    public function getPath(): string
    {
        return Path::join(
            BASE_PATH,
            $this->getOwner()->Config()->get('defaultRobotsDotTxtPath')
        );
    }

    /**
     * Returns a FieldList with the new fields
     *
     * @inheritdoc
     */
    public function updateCMSFields(FieldList $fields): void
    {
        # Status dropdown
        $robotsTxtStatusField = DropdownField::create('RobotsTxtStatus', 'Status', [
            0 => 'disabled',
            1 => 'enabled'
        ]);
        $robotsTxtStatusField->setRightTitle('Current environment: '. Director::get_environment_type());

        # Robots.txt (live) field
        $robotsTxtContentLiveField = TextareaField::create('RobotsTxtContentLive', 'Content (live)');
        $robotsTxtContentLiveField->setRows(10);

        # Robots.txt (test/dev) field
        $robotsTxtContentTestField = TextareaField::create('RobotsTxtContentTest', 'Content (test/dev)');
        $robotsTxtContentTestField->setRows(10);

        # Add the fields and rename the tab
        $fields->addFieldsToTab(
            'Root.RobotsTxt',
            [
                $robotsTxtStatusField,
                $robotsTxtContentLiveField,
                $robotsTxtContentTestField
            ]
        );
        $robotsTxtTab = $fields->fieldByName('Root.RobotsTxt');
        $robotsTxtTab->setTitle('Robots.txt');
    }

    /**
     * Create robots.txt according to the environment and toggle in the CMS.
     *
     * @inheritdoc
     */
    public function onBeforeWrite()
    {
        // Get the robots.txt absolute path.
        $filePath = Path::join($this->getPath(), 'robots.txt');

        // Return early if robots.txt is disabled in the CMS.
        if (boolval($this->getOwner()->RobotsTxtStatus) === false) {
            try {
                $this->disableRobotsDotTxt($filePath);
            } catch (Throwable $exception) {
                self::log_message('error', $exception);
            }

            return;
        }

        // We're enabled! so now lets get the environment specific rules for our robots.txt.
        // E.g. test/dev uses only RobotsTxtContentTest rules in our robots.txt.
        $content = Director::isLive()
            ? $this->getOwner()->RobotsTxtContentLive
            : $this->getOwner()->RobotsTxtContentTest;

        // Begin writing to our robots.txt
        try {
            $this->writeRobotsDotTxt($filePath, $content);
        } catch (Throwable $exception) {
            self::log_message('error', $exception);
        }
    }

    /**
     * Handle writing to robots.txt
     * @throws RuntimeException
     */
    private function writeRobotsDotTxt(string $filePath, string $content): void
    {
        if (!file_exists($filePath)) {
            throw new RuntimeException('robots.txt file does not exist: ' . $filePath);
        }

        $handle = fopen($filePath, 'w');

        if (!$handle) {
            throw new RuntimeException('Unable to open file for writing: ' . $filePath);
        }

        if (fwrite($handle, $content) === false) {
            fclose($handle);
            throw new RuntimeException('Unable to write content to file: ' . $filePath);
        }
    }

    /**
     * Handle deletion of the robots.txt if the CMS toggle is set to disable.
     */
    private function disableRobotsDotTxt(string $filePath): void
    {
        // Throw an exception if the file doesn't exist
        if (!file_exists($filePath)) {
            throw new RuntimeException('robots.txt file does not exist: ' . $filePath);
        }

        // We are in disabled mode, blank the robots.txt file if it exists
        try {
            $this->writeRobotsDotTxt($filePath, '');
        } catch (Throwable $exception) {
            self::log_message('error', $exception);
        }
    }

    /**
     * Log messages using the format for logging exceptions to Raygun
     *
     * @param string[] $data - Any additional data needed to be recorded to raygun.
     */
    public static function log_message(string $type, Throwable $exception, ?array $data = []): void
    {
        $logger = Injector::inst()->get(LoggerInterface::class);
        $logger->$type(
            $exception->getMessage(),
            array_merge([
                'exception' => $exception,
                'tags' => self::RAYGUN_TAG,
            ], $data)
        );
    }

}

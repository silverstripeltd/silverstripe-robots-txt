<?php

namespace Odorisioe\SilverStripe\RobotsTxt\Tests\Extensions;

use Generator;
use Odorisioe\SilverStripe\RobotsTxt\Extensions\SiteConfigExtension;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Path;
use SilverStripe\Control\HTTPStreamResponse;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Core\Validation\ValidationException;
use SilverStripe\SiteConfig\SiteConfig;

class SiteConfigExtensionTest extends SapphireTest
{

    protected static $fixture_file = 'SiteConfigExtensionTest.yml';

    /**
     * Set a static value of our test robots.txt file path for reuse in our tests.
     * It will be bound to our configurable class member {@see SiteConfigExtension::$defaultRobotsDotTxtPath}.
     */
    protected static string $test_robots_txt_path = '/tests/assets';

    protected $siteConfig;

    protected static $required_extensions = [
        SiteConfig::class => [
            SiteConfigExtension::class,
        ],
    ];

    public function setUp(): void
    {
        parent::setUp();

        // Mocking our site config for reuse in our tests.
        $this->siteConfig = $this->objFromFixture(SiteConfig::class, 'site_config');

        // Create our test robots.txt file if it does not exist.
        if (!file_exists($this->getTestRobotsTxtAbsolutePath($this->siteConfig))) {
            file_put_contents($this->getTestRobotsTxtAbsolutePath($this->siteConfig), '');
        }
    }

    /**
     * Set this up before the class is initialized, so when we use,
     * {@see Configurable::config()} it will set our test robots.txt file path.
     *
     * @throws \Exception
     */
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        // Set all tests to use a mocked robots.txt file path... we don't want to override the actual robots.txt!
        Config::modify()->set(SiteConfigExtension::class, 'defaultRobotsDotTxtPath', self::$test_robots_txt_path );
    }

    /**
     * Tests the default robots.txt base path e.g. `/var/wwww/html/tests/assets/`
     */
    public function testGetPath(): void
    {
        // Get our test robots.txt file for our expected path
        $expected = Path::join(BASE_PATH, self::$test_robots_txt_path);

        /** Use our test robots.txt file path {@see SiteConfigExtension::getPath()} instance */
        $this->assertEquals($expected, $this->siteConfig->getPath());
    }

    /**
     * @throws ValidationException
     * @dataProvider robotsTxtStatusDataProvider
     */
    public function testOnBeforeWrite(int $status, string $expected): void
    {
        // Update the SiteConfig so {@see onBeforeWrite} will be called based on our RobotsTxtStatus toggle.
        $this->siteConfig->update(['RobotsTxtStatus' => $status]);
        $this->siteConfig->write();

        // Get our test robots.txt file
        $path = $this->getTestRobotsTxtAbsolutePath($this->siteConfig);
        $this->assertFileExists($path);

        // Begin to validate the content of our test file
        $stream = fopen($path, "r");
        $response = new HTTPStreamResponse($stream, filesize($path));

        // Test body (should parse stream directly into memory)
        $this->assertEquals($expected, $response->getBody());
    }

    public function robotsTxtStatusDataProvider(): Generator
    {
        yield 'Enabled' => [
            'RobotsTxtStatus' => 1,
            'expected' => 'My dev/test only robots.txt content.'
        ];

        yield 'Disabled' => [
            'RobotsTxtStatus' => 0,
            'expected' => ''
        ];
    }

    /**
     * Helper so we can access our test robots.txt file path.
     */
    private function getTestRobotsTxtAbsolutePath(SiteConfig $config): string
    {
        return Path::join($config->getPath(), 'robots.txt');
    }

}

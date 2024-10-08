<?php

namespace Odorisioe\SilverStripe\RobotsTxt\Tests\Extensions;

use Generator;
use Odorisioe\SilverStripe\RobotsTxt\Extensions\SiteConfigExtension;
use SilverStripe\Core\Path;
use SilverStripe\Control\HTTPStreamResponse;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\ValidationException;
use SilverStripe\SiteConfig\SiteConfig;

class SiteConfigExtensionTest extends SapphireTest
{

    protected static $fixture_file = 'SiteConfigExtensionTest.yml';

    protected ?string $defaultRobotsDotTxtPath = 'tests/assets';

    protected $siteConfig;

    protected static $required_extensions = [
        SiteConfig::class => [
            SiteConfigExtension::class,
        ],
    ];

    public function setUp(): void
    {
        parent::setUp();

        // Set all tests to use a mocked robots.txt file path... we don't want to override the actual robots.txt!
        Config::modify()->set(SiteConfigExtension::class, 'robots_txt_path', $this->defaultRobotsDotTxtPath);

        // Create our test robots.txt file
        if (!file_exists($this->getRobotsTxtPath(SiteConfig::current_site_config()))) {
            file_put_contents($this->getRobotsTxtPath(SiteConfig::current_site_config()), '');
        }

        // Mocking our site config for reuse in our tests.
        $this->siteConfig = $this->objFromFixture(SiteConfig::class, 'site_config');
    }

    public function testGetPath(): void
    {
        $config = $this->siteConfig->current_site_config();

        $expected = Path::join(BASE_PATH, $this->defaultRobotsDotTxtPath);
        $this->assertEquals($expected, $config->getPath());
    }

    /**
     * @throws ValidationException
     * @dataProvider robotsTxtStatusDataProvider
     */
    public function testOnBeforeWrite(int $status, string $expected): void
    {
        // Mocking the owner to return false for RobotsTxtStatus
        $this->siteConfig->current_site_config();
        $this->siteConfig->update(['RobotsTxtStatus' => $status]);
        $this->siteConfig->write();

        // Call onBeforeWrite()
        $this->siteConfig->onBeforeWrite();

        // Get our robots.txt file
        $path = $this->siteConfig->getPath() . '/robots.txt';
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
            'RobotosTxtStatus' => 1,
            'expected' => 'My dev/test only robots.txt content.'
        ];

        yield 'Disabled' => [
            'RobotosTxtStatus' => 0,
            'expected' => ''
        ];
    }

    /**
     * Helper so we can access our test robots.txt file path.
     */
    private function getRobotsTxtPath(SiteConfig $config): string
    {
        return Path::join($config->getPath(), 'robots.txt');
    }

}

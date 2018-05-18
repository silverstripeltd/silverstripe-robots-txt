<?php

namespace Odorisioe\SilverStripe\RobotsTxt\Model;

use SilverStripe\ORM\DataExtension;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\TextareaField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Core\Environment;

/**
 * SiteConfig extension to manage the robots.txt file
 *
 * @author Emmanuel Odorisio <odorisioe@gmail.com>
 * @package silverstripe-robots-txt
 */

class SiteConfigExtension extends DataExtension
{
  private static $db = [
    'RobotsTxtStatus' => 'Int',
		'RobotsTxtContentLive' => 'Text',
		'RobotsTxtContentTest' => 'Text'
	];

  private static $defaults = [
    'RobotsTxtStatus' => 1,
  ];

  /**
   * Returns a FieldList with the new fields
   *
   * @return FieldList
   */
  public function updateCMSFields(FieldList $fields)
  {
    # Status dropdown
    $robotsTxtStatusField = DropdownField::create('RobotsTxtStatus', 'Status', [
      0 => 'disabled',
      1 => 'enabled'
    ]);
    $robotsTxtStatusField->setRightTitle('Current environment: '.strtolower(Environment::getEnv('SS_ENVIRONMENT_TYPE')));
    $fields->addFieldToTab('Root.RobotsTxt', $robotsTxtStatusField);

    # Robots.txt (live) field
    $robotsTxtContentLiveField = TextareaField::create('RobotsTxtContentLive', 'Content (live)');
    $robotsTxtContentLiveField->setRows(10);
    $fields->addFieldToTab('Root.RobotsTxt', $robotsTxtContentLiveField);

    # Robots.txt (test/dev) field
    $robotsTxtContentTestField = TextareaField::create('RobotsTxtContentTest', 'Content (test/dev)');
    $robotsTxtContentTestField->setRows(10);
    $fields->addFieldToTab('Root.RobotsTxt', $robotsTxtContentTestField);

    # Rename tab
    $robotsTxtTab = $fields->fieldByName('Root.RobotsTxt');
    $robotsTxtTab->setTitle('Robots.txt');

    return $fields;
  }

  /**
   * Create robots.txt according to the
   *
   */
  public function onBeforeWrite()
  {
    $filepath = PUBLIC_PATH.'/robots.txt';
    $status = $this->owner->RobotsTxtStatus;

    if ($status == 1) {
      $env = strtolower(Environment::getEnv('SS_ENVIRONMENT_TYPE'));
      $content = $this->owner->RobotsTxtContentTest;

      if ($env == 'live') {
        $content = $this->owner->RobotsTxtContentLive;
      }

      $handle = fopen($filepath, 'w+');
      fwrite($handle, $content);
      fclose($handle);

    } else {
      if (file_exists($filepath)) {
        @unlink($filepath);
      }
    }

  }

}

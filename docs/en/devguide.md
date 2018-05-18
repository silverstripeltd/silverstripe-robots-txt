# SilverStripe Robots.txt Documentation

## Developer guide

### Disable module
Add the code below on *mysite/_config.php*
```php
use Silverstripe\SiteConfig\SiteConfig;
SiteConfig::remove_extension('Odorisioe\SilverStripe\RobotsTxt\Model\SiteConfigExtension');
 ```

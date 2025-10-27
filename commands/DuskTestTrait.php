<?php

namespace Blocs\Commands;

use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Illuminate\Support\Collection;
use Laravel\Dusk\Chrome\SupportsChrome;
use Laravel\Dusk\Concerns\ProvidesBrowser;
use PHPUnit\Framework\Attributes\BeforeClass;

trait DuskTestTrait
{
    use ProvidesBrowser;
    use SupportsChrome;

    /**
     * Prepare for Dusk test execution.
     */
    #[BeforeClass]
    public static function prepare(): void
    {
        if (! static::runningInSail()) {
            static::startChromeDriver(['--port=9515']);
        }
    }

    /**
     * Create the RemoteWebDriver instance.
     */
    protected function driver(): RemoteWebDriver
    {
        $options = (new ChromeOptions)->addArguments(collect([
            $this->shouldStartMaximized() ? '--start-maximized' : '--window-size=1920,1080',
            '--disable-search-engine-choice-screen',
        ])->unless($this->hasHeadlessDisabled(), function (Collection $items) {
            return $items->merge([
                '--disable-gpu',
                '--headless=new',
            ]);
        })->all());

        return RemoteWebDriver::create(
            $_ENV['DUSK_DRIVER_URL'] ?? env('DUSK_DRIVER_URL') ?? 'http://localhost:9515',
            DesiredCapabilities::chrome()->setCapability(
                ChromeOptions::CAPABILITY, $options
            )
        );
    }

    /**
     * Determine whether the Dusk command has disabled headless mode.
     */
    protected function hasHeadlessDisabled(): bool
    {
        return true;
    }

    /**
     * Determine if the browser window should start maximized.
     */
    protected function shouldStartMaximized(): bool
    {
        return false;
    }

    /**
     * Determine if the tests are running within Laravel Sail.
     *
     * @return bool
     */
    protected static function runningInSail()
    {
        return isset($_ENV['LARAVEL_SAIL']) && $_ENV['LARAVEL_SAIL'] == '1';
    }

    private function openBrowser()
    {
        $this->prepare();

        $this->browser = $this->newBrowser($this->createWebDriver());
        $this->browser::$storeScreenshotsAt = base_path('tests/Browser/screenshots');
        $this->browser::$storeSourceAt = base_path('tests/Browser/source');
    }
}

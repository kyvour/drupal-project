<?php

/**
 * @file
 * Contains \DrupalProject\composer\ScriptHandler.
 */

namespace DrupalProject\composer;

use Composer\Script\Event;
use Composer\Semver\Comparator;
use DrupalFinder\DrupalFinder;
use Symfony\Component\Filesystem\Filesystem;
use Webmozart\PathUtil\Path;

class ScriptHandler {

  protected static $drupalDirs = [
    'files',
    'libraries',
    'modules/contrib',
    'modules/custom',
    'profiles/contrib',
    'profiles/custom',
    'themes/contrib',
    'themes/custom',
    '../config/sync',
    '../drush/contrib',
    '../drush/custom',
    '../private',
    '../tmp',
  ];

  /**
   * @param \Composer\Script\Event $event
   */
  public static function createRequiredDirs(Event $event) {
    $drupalFinder = new DrupalFinder();
    if (!$drupalFinder->locateRoot(getcwd())) {
      throw new \RuntimeException('Unable to find Drupal installation.');
    }

    $drupalRoot = $drupalFinder->getDrupalRoot();
    $fs = new Filesystem();

    // Create required directories inside DRUPAL_ROOT.
    foreach (self::drupalDirs($event) as $dir) {
      $path = Path::makeRelative($dir, $drupalRoot);
      $path = Path::canonicalize("$drupalRoot/$path");

      if (!$fs->exists($path)) {
        $fs->mkdir($path);
        $fs->touch("$path/.keep");
      }
    }
  }

  /**
   * Returns array with required dirs.
   *
   * @return array
   */
  protected static function drupalDirs(Event $event) {
    $extra = (array) $event->getComposer()->getPackage()->getExtra();

    if (!isset($extra['drupal-project']['drupal-dirs'])) {
      return self::$drupalDirs;
    }

    if (!is_array($extra['drupal-project']['drupal-dirs'])) {
      return [];
    }

    return $extra['drupal-project']['drupal-dirs'];
  }

  /**
   * Checks if the installed version of Composer is compatible.
   *
   * Composer 1.0.0 and higher consider a `composer install` without having a
   * lock file present as equal to `composer update`. We do not ship with a lock
   * file to avoid merge conflicts downstream, meaning that if a project is
   * installed with an older version of Composer the scaffolding of Drupal will
   * not be triggered. We check this here instead of in drupal-scaffold to be
   * able to give immediate feedback to the end user, rather than failing the
   * installation after going through the lengthy process of compiling and
   * downloading the Composer dependencies.
   *
   * @see https://github.com/composer/composer/pull/5035
   */
  public static function checkComposerVersion(Event $event) {
    $composer = $event->getComposer();
    $io = $event->getIO();

    $version = $composer::VERSION;

    // The dev-channel of composer uses the git revision as version number,
    // try to the branch alias instead.
    if (preg_match('/^[0-9a-f]{40}$/i', $version)) {
      $version = $composer::BRANCH_ALIAS_VERSION;
    }

    // If Composer is installed through git we have no easy way to determine if
    // it is new enough, just display a warning.
    if ($version === '@package_version@' || $version === '@package_branch_alias_version@') {
      $io->writeError('<warning>You are running a development version of Composer. If you experience problems, please update Composer to the latest stable version.</warning>');
    }
    elseif (Comparator::lessThan($version, '1.0.0')) {
      $io->writeError('<error>Drupal-project requires Composer version 1.0.0 or higher. Please update your Composer before continuing</error>.');
      exit(1);
    }
  }

}

# Development and testing

## Translations

Interface translations are managed through Transifex and distributed separately
through CiviCRM. Starting with 2.1.6, release archives do not bundle Gettext
catalogues under `l10n/`.

Documentation translations are maintained as separate evergreen MkDocs
editions. The French edition is published from its own documentation repository.

## Continuous integration

The CI resolves the latest public stable `mjwshared` tag for each run. Its
default matrix tests PHP 8.1 through 8.5 with the latest CiviCRM release that
satisfies `^6.14`.

The PHPUnit workflow can also target a specific CiviCRM version:

- `civicrm-version`: `6.14.2` for the supported minimum, an explicit alpha or
  beta, or `dev-master`.
- `php-version`: `matrix` for every supported version, or one PHP version valid
  for that CiviCRM target. Use PHP 8.4 for CiviCRM 6.14.2.

Historical CiviCRM tests may install dependencies affected by known security
advisories. That exception is limited to the disposable CiviCRM CI checkout;
the extension retains its Composer security policy. `composer audit` is still
reported. Such a run validates compatibility, not the security of an obsolete
CiviCRM release.

## Local tests

Run fast unit tests:

```bash
phpunit -c phpunit.xml.dist
```

Run integration tests against a bootstrapped CiviCRM database:

```bash
phpunit -c phpunit-integration.xml
```

Integration tests manipulate the configured database inside isolated
transactions. Never run them against production.

## Documentation preview

Preview this guide with the CiviCRM MkDocs container:

```bash
docker run --rm -v "$PWD:/docs" -p 8000:8000 -w /docs \
  mjcoltd/civicrm-docker-mkdocs serve --dirtyreload -a 0.0.0.0
```

Documentation is evergreen. Update `main` and mention the first extension or
CiviCRM version affected when a behavior changed significantly.

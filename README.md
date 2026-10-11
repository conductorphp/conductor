Conductor
=========

The conductor application: every `conductor/*` module installed in one place, run against an
application's configuration directory. This is what a project installs (as `conductor.phar`, an
image, or `composer global require conductor/conductor`); a project's own `tools/<app>-conductor/`
is configuration only, `config/app/**`, with no PHP and no composer.json of its own.

```bash
cd tools/middleware-conductor && conductor list          # the working directory is the configuration
conductor --config /path/to/tools/magento-conductor app:deploy --plan development
CONDUCTOR_CONFIG_DIR=/path/to/tools/magento-conductor conductor app:config:show
```

Which platform, filesystem, database and VCS adapters are active is decided by the configuration
(`application.platform`, `default_filesystem`, `database.adapters`, `vcs.default_adapter`), not by
what is installed: every module is. A module whose runtime the host lacks (mydumper, git, a PHP
extension) is refused when the configuration selects it, naming what to install.

* [conductor/core documentation](https://github.com/conductorphp/conductor-core/blob/master/docs/index.md):
  the configuration directory, environment selection, `${VAR}` interpolation
* [conductor/application-orchestration](https://github.com/conductorphp/conductor-application-orchestration):
  plans, deploys, snapshots

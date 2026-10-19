[1.3.0](https://github.com/conductorphp/conductor/compare/1.2.0...1.3.0) (2026-10-19)

### Features
* conductor-application-orchestration: a snapshot restore empties an existing database instead of dropping it (CTAP-2756) ([4a8c907](https://github.com/conductorphp/conductor/commit/4a8c9071f79aad03b8948453a6acfccf76438fa1))
* conductor-core: DatabaseEmptyingInterface empties a database without dropping it (CTAP-2756) ([90cb0ae](https://github.com/conductorphp/conductor/commit/90cb0ae623e71272300560f104a4448026f714cb))
* conductor-mysql-database-support: emptyDatabase() drops every table, view, routine and event in place (CTAP-2756) ([5f6d5bd](https://github.com/conductorphp/conductor/commit/5f6d5bdc30f5feade2bcb6400c2ab990f5691310))

<!--- CHANGELOG SPLIT MARKER -->

[1.2.0](https://github.com/conductorphp/conductor/compare/1.1.1...1.2.0) (2026-10-19)

### Features
* conductor-magento-2-platform-support: graphql_id_salt renders cache/graphql/id_salt into env.php (CTAP-2733) ([52ed35f](https://github.com/conductorphp/conductor/commit/52ed35fdd4ac67ec7a4e26739d24b8308fb84e83))

<!--- CHANGELOG SPLIT MARKER -->

[1.1.1](https://github.com/conductorphp/conductor/compare/1.1.0...1.1.1) (2026-10-15)

### Bug Fixes
* conductor-application-orchestration: app:snapshot --replace deletes the old snapshot (CTAP-2586) ([5b105ee](https://github.com/conductorphp/conductor/commit/5b105ee04491b733304b17bf7db3234028ea6829))

<!--- CHANGELOG SPLIT MARKER -->

[1.1.0](https://github.com/conductorphp/conductor/compare/1.0.4...1.1.0) (2026-10-15)

### Features
* conductor-application-orchestration: dump a database before a restore drops it (CTAP-2579) ([0e863df](https://github.com/conductorphp/conductor/commit/0e863df66a85be865431ca64d818547068dc3980))

<!--- CHANGELOG SPLIT MARKER -->

[1.0.4](https://github.com/conductorphp/conductor/compare/1.0.3...1.0.4) (2026-10-12)

### Bug Fixes
* release with the modules the phar ships (CTAP-2418) ([6296df5](https://github.com/conductorphp/conductor/commit/6296df516f8975b1ebdc669cada7b21f1af998cf))

<!--- CHANGELOG SPLIT MARKER -->

[1.0.3](https://github.com/conductorphp/conductor/compare/1.0.2...1.0.3) (2026-10-12)

### Bug Fixes
* phar keeps vendor directories named Test or doc (CTAP-2395) ([afc3400](https://github.com/conductorphp/conductor/commit/afc3400b89d8ee9a2cb65eabe2e1cbc49817098c))

<!--- CHANGELOG SPLIT MARKER -->

[1.0.2](https://github.com/conductorphp/conductor/compare/1.0.1...1.0.2) (2026-10-12)

### Bug Fixes
* ${VAR} before decrypting the config (CTAP-2383) ([583bf05](https://github.com/conductorphp/conductor/commit/583bf05808c5c301d714ac362f54095ac8625ec1))

<!--- CHANGELOG SPLIT MARKER -->

[1.0.1](https://github.com/conductorphp/conductor/compare/1.0.0...1.0.1) (2026-10-11)

### Bug Fixes
* the released majors of aws-s3, azure, mysql and ssh (CTAP-1763) ([0f9dcc8](https://github.com/conductorphp/conductor/commit/0f9dcc80964a7fed5761e95cf7ef0f624312b68c))

<!--- CHANGELOG SPLIT MARKER -->

[1.0.0](https://github.com/conductorphp/conductor/compare/0.0.0...1.0.0) (2026-10-11)

### Features
* the clicktap platform module (CTAP-1760) ([dbf6897](https://github.com/conductorphp/conductor/commit/dbf689722a1a4cacd0e0ed251e957f89a8f60090))
* limit for the static runtime (CTAP-1782) ([2819cb1](https://github.com/conductorphp/conductor/commit/2819cb182254d4bb5906be373427cfd5efbc5e05))
* configuration; --version needs no configuration directory (CTAP-1761) ([fb6515a](https://github.com/conductorphp/conductor/commit/fb6515aa69dc5875a963be6e1ca8b049efe8cc8f))
* conductor application (CTAP-1760) ([5223b53](https://github.com/conductorphp/conductor/commit/5223b53235c060f86b6d83e7fa75e05b469625ab))
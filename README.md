# Lookout

Self-hosted, Laravel-native observability for requests, exceptions, queries, jobs, commands, scheduled tasks, cache, mail, notifications, logs, and outgoing HTTP calls.

## Storage Drivers

Lookout uses SQLite by default and can also store data in a host-managed MySQL or PostgreSQL connection.

SQLite remains the recommended default for local development, demos, and small applications. MySQL or PostgreSQL are better fits when the dashboard is used in a higher-volume production app and the host already operates those databases.

Lookout does not migrate existing observability data between storage drivers. Choose the driver before enabling the package in a persistent environment, or migrate the data manually.

### Default SQLite

```env
LOOKOUT_STORAGE_DRIVER=sqlite
LOOKOUT_STORAGE_CONNECTION=lookout
LOOKOUT_STORAGE_PATH=/absolute/path/to/storage/lookout/lookout.sqlite
```

With SQLite, `lookout:install` creates the storage directory and SQLite file when they do not exist.

### Existing App Database

```env
LOOKOUT_STORAGE_DRIVER=mysql
LOOKOUT_STORAGE_CONNECTION=mysql
```

or:

```env
LOOKOUT_STORAGE_DRIVER=pgsql
LOOKOUT_STORAGE_CONNECTION=pgsql
```

In this mode, Lookout uses the existing Laravel connection and does not create a SQLite file.

### Dedicated Lookout Connection

Define a normal Laravel database connection named `lookout`, then point Lookout at it:

```env
LOOKOUT_STORAGE_DRIVER=pgsql
LOOKOUT_STORAGE_CONNECTION=lookout
```

The package migrations run against `LOOKOUT_STORAGE_CONNECTION`.

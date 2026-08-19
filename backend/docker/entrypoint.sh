#!/bin/sh
set -eu

# Everything the API needs to exist before it answers its first request.
#
# It runs here rather than in the image because both steps are about *this*
# database, and the database lives in a volume that outlives the image. A
# container started against an empty volume gets its tables and a machine; one
# started against a volume that already has both changes nothing.
#
# Both commands are safe to repeat, which is the property that matters: this
# runs on every start, including restarts and scale-ups, and neither of them
# may undo what the previous run did.

# Migrating as the same user that serves traffic has nothing to separate here:
# the database is a file this user already owns and could overwrite without
# Doctrine's help. The day the DSN points at a real server, that stops being
# true and the migration should run with its own credentials — DDL for one, DML
# for the other.
echo '[entrypoint] applying migrations'
php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration

echo '[entrypoint] making sure there is a machine to serve'
php bin/console app:machine:provision

echo '[entrypoint] handing over to the server'
exec "$@"

# End-to-end tests

Reserved, deliberately empty. Playwright is not installed and no dependency here
is declared until there is a reason to run a browser in CI.

The folder exists now so that adding E2E later is adding files rather than
reorganising the project: `frontend/src` holds the unit and component tests
beside the code they cover, and anything that drives a real browser against a
running stack belongs here instead. Ticket 13b builds the container these would
run against.

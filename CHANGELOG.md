# Changelog

## 0.10.0 – 2026-11-12

### Changed

- Released from the [denosyscore/framework](https://github.com/denosyscore/framework)
  monorepo, where this package now lives under `packages/queue`. All DenoSys
  packages share one version line, so the version moves from v0.3.3 to
  0.10.0. There are no API changes in this release.
- Requirements on other DenoSys packages are `^0.10`.

## 0.3.3 – 2026-11-11

- Accept `denosyscore/database` 0.5. The test suite passes against 0.5.0.

## 0.3.2

- Accept database 0.4 while retaining the supported 0.2 and 0.3 lines.

## 0.3.1

- Accept database 0.3 while retaining the supported 0.2 line.

## v0.3.0

- Claim database jobs conditionally across workers and reclaim reservations
  after the configured positive `retry_after` lease.
- Add `ContainerAwareJob` and `JobRunner` so serialized jobs can resolve
  services from the bootstrapped container at execution time. Existing
  `handle()` jobs remain supported.
- Use the runner in the synchronous queue managed by `QueueManager`; database
  workers must invoke the runner after popping jobs.
- Add regression coverage for payload serialization, database retry, sync
  dispatch, and legacy job execution.

- Declare the UUID dependency used by `Job` so jobs can be constructed after
  a clean Composer install.
- Add a job-construction regression test and CI test gate.

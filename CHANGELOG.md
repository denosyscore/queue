# Changelog

## Unreleased (proposed 0.3.0)

- Add `ContainerAwareJob` and `JobRunner` so serialized jobs can resolve
  services from the bootstrapped container at execution time. Existing
  `handle()` jobs remain supported.
- Use the runner in the synchronous queue managed by `QueueManager`; database
  workers must invoke the runner after popping jobs.
- Add regression coverage for payload serialization, database retry, sync
  dispatch, and legacy job execution.

## Unreleased (proposed 0.2.3)

- Declare the UUID dependency used by `Job` so jobs can be constructed after
  a clean Composer install.
- Add a job-construction regression test and CI test gate.

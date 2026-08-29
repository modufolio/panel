
### Correction: the panel package always had tests (2026-08-25)

Several entries above claim the panel package had "zero tests" and count a
package suite growing from nothing. **That was wrong.** The package has its
own `tests/` directory and its own `vitest.config.ts`, and has carried 32
spec files / 479 tests throughout — including `useQuery.spec.ts`,
`useAsyncData.spec.ts`, `reconcile.spec.ts` and `optimistic.spec.ts`, the
very modules described here as untested.

Two scoped-wrong checks produced the error and agreed with each other: a
`find src -name "*.test.ts"` that never looked outside `src/`, and a root
`vitest run` whose `include` only covered `assets/**`. Neither would have
caught it; running the package's own suite would have.

Consequences cleaned up:

- The duplicate specs written on that premise (`useQuery`, `useAsyncData`,
  `reconcile`) are deleted — the existing ones cover the same ground, down
  to the invalidation-coalescing case.
- Genuinely new specs (writeGate, moduleSingleton, usePendingKeys,
  useFieldSaver ordering, optimistic ordering, unsaved-changes, belongs-to
  revert and create, blueprint row errors) moved from `src/` into `tests/`
  where the package's own runner sees them: 42 files, 530 tests.
- The root `vitest.config.ts` include added for the misplaced files is
  reverted.

**And the consolidation found a real bug.** Adding files changed timing
enough to expose an intermittently failing pre-existing spec: `useQuery`
skipped a revalidation when a subscriber arrived in the *same millisecond*
as the previous fetch, because `Date.now() - updatedAt > staleTime` reads as
"still fresh" at zero elapsed — so the default `staleTime: 0` did not
reliably mean "always revalidate". Now `>=`. Three consecutive full runs
green.

The claim to keep from those entries is narrower but still true: the package
had **no component (`.vue`) mounting tests** before this work, and now has
them for BlueprintForm and BelongsToSelect.

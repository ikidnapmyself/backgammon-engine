# Contributing

Run `composer test` before you open a pull request.

- One change per pull request.
- Add a test for every change in behavior.
- Example bots are welcome in `examples/`.
- Rule changes must match standard backgammon rules.
- Move generation order is stable on purpose. Callers rely on it, so don't reorder it.

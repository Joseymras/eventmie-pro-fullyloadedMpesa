# Core change record

- No files under `vendor/` were edited.
- Existing app-owned overrides in `app/Http/Controllers/Eventmie/BookingsController.php` and the Eventmie Vue override under `resources/js/vendor/eventmie-pro/` were used for checkout.
- The pre-existing feature-branch change in the bundled path dependency `eventmie-pro/src/Models/Booking.php` was carried forward when the feature branch was fast-forwarded to `master`; it was not introduced by this payment hardening. Treat this checked-in package-source customization as a merge-sensitive upgrade point.
- Laravel configuration, migrations, commands, jobs, and models added by this change are app-owned.

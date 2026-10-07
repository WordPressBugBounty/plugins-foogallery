# Action Scheduler 4.1.0

Runtime source from https://github.com/woocommerce/action-scheduler/releases/tag/4.1.0
Downloaded from https://github.com/woocommerce/action-scheduler/archive/refs/tags/4.1.0.tar.gz
Archive SHA-256: `00acecf53aebe9c10626654e93e222e424406facce6d5375dc6d255fc1f2a360`

FooGallery adds a defensive logger-class validation in
`classes/abstracts/ActionScheduler_Logger.php`. Invalid classes returned by the public
`action_scheduler_logger_class` filter fall back to `ActionScheduler_wpCommentLogger`
instead of causing a fatal error during WordPress bootstrap.

Includes bootstrap, functions, classes, deprecated compatibility code, lib, license,
readme and changelog. Repository agent instructions are not distributed.
Licensed GPL-3.0-or-later (see license.txt). Loaded outside PHP-Scoper to preserve
native version negotiation. The Free and PRO package include lib/**/*.

<?php

namespace App\Sync;

use RuntimeException;

/** The network is still preparing an asynchronous report. The sync is retried later instead of failing. */
class ReportPending extends RuntimeException {}

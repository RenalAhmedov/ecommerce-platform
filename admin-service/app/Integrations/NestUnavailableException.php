<?php

namespace App\Integrations;

use RuntimeException;

/** The api-gateway could not answer: unreachable, an error status, or a GraphQL error. */
class NestUnavailableException extends RuntimeException {}

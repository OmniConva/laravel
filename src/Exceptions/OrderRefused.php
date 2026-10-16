<?php

namespace OmniConva\Laravel\Exceptions;

/**
 * Throw from your OrderHandler to refuse an order ("Outside our delivery
 * area"). Its message is sent to OmniConva and shown to the store owner.
 * Any other exception is answered as a generic failure, so a database
 * error's text (SQL, table names, customer data) stays in your own logs.
 */
class OrderRefused extends OmniConvaException {}

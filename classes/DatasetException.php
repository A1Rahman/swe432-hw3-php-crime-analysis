<?php

/**
 * Custom exception thrown whenever the uploaded dataset is missing,
 * unreadable, or not in the expected format.
 * Using our own type lets index.php tell "bad upload" problems apart
 * from unexpected programming errors.
 */
class DatasetException extends Exception
{
}

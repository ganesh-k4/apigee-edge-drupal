<?php

namespace Drupal\apigee_edge;

/**
 * Class Job
 *
 * @package Drupal\apigee_edge
 */
abstract class Job {

  /**
   * Job is waiting to be picked up by a worker.
   */
  public const IDLE = 0;

  /**
   * Job failed, waiting to be retried.
   */
  public const RESCHEDULED = 1;

  /**
   * Job is claimed by a worker, but not running yet.
   */
  public const SELECTED = 2;

  /**
   * Job is running.
   */
  public const RUNNING = 3;

  /**
   * Job is failed, and it won't be retried.
   */
  public const FAILED = 4;

  /**
   * Job is finished successfully.
   */
  public const FINISHED = 5;

  protected const ALL_STATUSES = [
    self::IDLE,
    self::RESCHEDULED,
    self::SELECTED,
    self::RUNNING,
    self::FAILED,
    self::FINISHED,
  ];

  /**
   * Exception storage.
   *
   * @var array
   */
  protected $exceptions = [];

  /**
   * Messages storage.
   *
   * @var string[]
   */
  protected $messages = [];

  /**
   * Job ID.
   *
   * @var string
   *   UUID of the job.
   */
  private $id;

  /**
   * The tag of the job.
   *
   * @var string
   */
  private $tag;

  /**
   * Remaining retries.
   *
   * @var int
   */
  protected $retry = 3;

  /**
   * Job status.
   *
   * @var int
   */
  protected $status = self::IDLE;

  /**
   * Returns the job's id.
   *
   * @return string
   *   UUID of the job.
   */
  public function getId() : string {
    return $this->id;
  }

  /**
   * Job tag.
   *
   * The job tag can be used to group multiple jobs together.
   *
   * @return string
   */
  public function getTag() : string {
    return $this->tag;
  }

  /**
   * Sets the job tag.
   *
   * @param string $tag
   */
  public function setTag(string $tag) {
    $this->tag = $tag;
  }

  /**
   * Returns the status of the job.
   *
   * @return int
   */
  public function getStatus() : int {
    return $this->status;
  }

  /**
   * Sets the job status.
   *
   * @param int $status
   */
  public function setStatus(int $status) {
    if (!in_array($status, self::ALL_STATUSES)) {
      throw new \LogicException('Invalid status');
    }

    $this->status = $status;
  }

  /**
   * Adds an exception to the exception storage.
   *
   * @param \Exception $exception
   */
  public function recordException(\Exception $exception) {
    $this->exceptions[] = [
      'code' => $exception->getCode(),
      'message' => $exception->getMessage(),
      'file' => $exception->getFile(),
      'line' => $exception->getLine(),
      'trace' => $exception->getTraceAsString(),
    ];
  }

  /**
   * Returns all stored exception data.
   *
   * @return array
   */
  public function getExceptions(): array {
    return $this->exceptions;
  }

  /**
   * Adds a message to the message storage.
   *
   * @param string $message
   */
  public function recordMessage(string $message) {
    $this->messages[] = $message;
  }

  /**
   * Returns all stored messages.
   *
   * @return string[]
   */
  public function getMessages() : array {
    return $this->messages;
  }

  /**
   * Job constructor.
   */
  public function __construct() {
    /** @var \Drupal\Component\Uuid\UuidInterface $uuid_service */
    $uuid_service = \Drupal::service('uuid');
    $this->id = $uuid_service->generate();
  }

  /**
   * Consumes a retry.
   *
   * @return bool
   *   Whether the job can be rescheduled.
   */
  public function consumeRetry() : bool {
    if ($this->retry > 0) {
      $this->retry--;
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Whether this job should be retried when an exception is thrown.
   *
   * @param \Exception $ex
   *
   * @return bool
   */
  public function shouldRetry(\Exception $ex) : bool {
    return TRUE;
  }

  /**
   * Executes this job.
   *
   * This function should be called only by the JobExecutor.
   *
   * @return bool
   *   Whether the job is incomplete. Returning TRUE here means that the job
   *   will be rescheduled.
   */
  abstract public function execute() : bool;

  /**
   * Returns this job's visual representation.
   *
   * @return array
   */
  abstract public function renderArray() : array;

  /**
   * Returns this job's textual representation.
   *
   * @return string
   */
  abstract public function __toString() : string;

}
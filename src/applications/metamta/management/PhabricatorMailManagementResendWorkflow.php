<?php

final class PhabricatorMailManagementResendWorkflow
  extends PhabricatorMailManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('resend')
      ->setSynopsis('Send mail again.')
      ->setExamples(
        '**resend** --id 1 --id 2')
      ->setArguments(
        array(
          array(
            'name'    => 'id',
            'param'   => 'id',
            'help'    => 'Send mail with a given ID again.',
            'repeat'  => true,
          ),
        ));
  }

  public function execute(PhutilArgumentParser $args) {
    $console = PhutilConsole::getConsole();

    $ids = $args->getArg('id');
    if (!$ids) {
      throw new PhutilArgumentUsageException(
        "Use the '--id' flag to specify one or more messages to resend.");
    }

    $messages = id(new PhabricatorMetaMTAMail())->loadAllWhere(
      'id IN (%Ld)',
      $ids);

    if ($ids) {
      $ids = array_fuse($ids);
      $missing = array_diff_key($ids, $messages);
      if ($missing) {
        throw new PhutilArgumentUsageException(
          'Some specified messages do not exist: '.
          implode(', ', array_keys($missing)));
      }
    }

    foreach ($messages as $message) {
      $message->setStatus(PhabricatorMetaMTAMail::STATUS_QUEUE);
      $message->save();

      $mailer_task = PhabricatorWorker::scheduleTask(
        'PhabricatorMetaMTAWorker',
        $message->getID());

      $console->writeOut(
        "Queued message #%d for resend.\n",
        $message->getID());
    }
  }

}

<?php

class Slider implements SplObserver {

  public function update(SplSubject $subject) {
    #echo "notification status = " . $subject->getStatus();
    #var_dump($subject->getCms());
  }

}

?>

<?php

class Pipeline {
    private $stages = [];

    public function addStage(StageInterface $stage) {
        $this->stages[] = $stage;
    }

    public function run() {
        foreach ($this->stages as $stage) {
            $stage->execute();
        }
    }

    public function createWorkingDir() {
        // Logic for creating the pipeline-level directory
        return uniqid('pipeline_', true);
    }
}
?>

<?php
namespace App\Model;

use Formapro\Pvm\Node;
use Formapro\Pvm\Process as PvmProcess;
use Formapro\Pvm\Token;
use function Makasim\Values\add_value;
use function Makasim\Values\get_value;
use function Makasim\Values\set_value;

class Process extends PvmProcess
{
    /**
     * @param Node $node
     * @param JobTemplate $jobTemplate
     */
    public function addNodeJobTemplate(Node $node, JobTemplate $jobTemplate):void
    {
        if ($node->getProcess() !== $this) {
            throw new \LogicException('The node is not from this processes');
        }

        set_value($node, 'jobTemplateId', $jobTemplate->getTemplateId());

        $jobTemplateIds = get_value($this, 'jobTemplateIds');
        $jobTemplateIds[] = $jobTemplate->getTemplateId();
        $jobTemplateIds = array_unique($jobTemplateIds);

        set_value($this, 'jobTemplateIds', $jobTemplateIds);
    }

    public function addJob(Job $job)
    {
        add_value($this, 'jobIds', $job->getId(), $job->getTemplateId());
    }

    /**
     * @param Token $token
     *
     * @return string
     */
    public function getTokenJobId(Token $token):string
    {
        return $this->getNodeJobId($token->getTransition()->getTo());
    }

    /**
     * @param Node $node
     *
     * @return string
     */
    public function getNodeJobId(Node $node):string
    {
        $jobTemplateId = get_value($node, 'jobTemplateId');

        return get_value($node->getProcess(), 'jobIds.'.$jobTemplateId);
    }
}

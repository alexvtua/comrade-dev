import {Component, OnInit} from '@angular/core';
import {JobTemplate} from "../shared/job-template";
import {JobTemplateService} from "../shared/job-template.service";
import * as uuid from "uuid";
import {Router} from "@angular/router";
import 'rxjs/add/operator/catch';
import {Trigger} from "../shared/trigger";
import {CronTrigger} from "../shared/cron-trigger";
import {SimpleTrigger} from "../shared/simple-trigger";
import {Runner} from "../shared/runner";
import {ExclusivePolicy} from "../shared/exclusive-policy";
import {GracePeriodPolicy} from "../shared/grace-period-policy";
import {RetryFailedPolicy} from "../shared/retry-failed-policy";
import {RunSubJobsPolicy} from "../shared/run-sub-jobs-policy";
import {Title} from "@angular/platform-browser";

@Component({
  selector: 'template-new',
  templateUrl: './template-new.component.html',
})
export class TemplateNewComponent implements OnInit {
  jobTemplate: JobTemplate;
  submitted: boolean;
  message: string;
  addCronTrigger: boolean = false;
  addSimpleTrigger: boolean = false;
  addQueueRunner: boolean = false;
  addHttpRunner: boolean = false;
  addExclusivePolicy: boolean = false;
  addGracePeriodPolicy: boolean = false;
  addRetryFailedPolicy: boolean = false;
  addRunSubJobsPolicy: boolean = false;

  constructor(private jobTemplateService: JobTemplateService, private router: Router, private titleService: Title) {
    this.jobTemplate = new JobTemplate();
    this.jobTemplate.templateId = uuid.v4();
    this.jobTemplate.processTemplateId = uuid.v4();
    this.submitted = false;
    this.message = '';
  }

  ngOnInit():void {
      this.titleService.setTitle('JM. New template');
  }

  onFormChange(): void {
     this.message = '';
  }

  onSubmit() {
    this.submitted = true;

    this.jobTemplateService.create(this.jobTemplate)
        .catch(res => { throw res })
        .subscribe(
            res => this.router.navigate(['/template', this.jobTemplate.templateId, 'view', 'summary']),
            err => this.message = err
        );

    this.submitted = false;
  }

  triggerCronTrigger(): void {
      this.addCronTrigger = !this.addCronTrigger;
  }

  triggerSimpleTrigger(): void {
      this.addSimpleTrigger = !this.addSimpleTrigger;
  }

  triggerQueueRunner(): void {
      this.addQueueRunner = !this.addQueueRunner;
  }

  triggerHttpRunner(): void {
    this.addHttpRunner = !this.addHttpRunner;
  }

  triggerExclusivePolicy(): void {
      this.addExclusivePolicy = !this.addExclusivePolicy;
  }

  triggerGracePeriodPolicy(): void {
      this.addGracePeriodPolicy = !this.addGracePeriodPolicy;
  }

  triggerRetryFailedPolicy(): void {
      this.addRetryFailedPolicy = !this.addRetryFailedPolicy;
  }

  triggerRunSubJobsPolicy(): void {
      this.addRunSubJobsPolicy = !this.addRunSubJobsPolicy;
  }

  onTriggerAdded(trigger: Trigger) {
    this.jobTemplate.addTrigger(trigger);

    if (trigger instanceof CronTrigger) {
      this.addCronTrigger = false;
    }
    if (trigger instanceof SimpleTrigger) {
        this.addSimpleTrigger = false;
    }
  }

  onExclusivePolicyAdded(policy: ExclusivePolicy) {
    this.jobTemplate.exclusivePolicy = policy;

    this.addExclusivePolicy = false;
  }

  onGracePeriodPolicyAdded(policy: GracePeriodPolicy) {
      this.jobTemplate.gracePeriodPolicy = policy;

      this.addGracePeriodPolicy = false;
  }

  onRetryFailedPolicyAdded(policy: RetryFailedPolicy) {
      this.jobTemplate.retryFailedPolicy = policy;

      this.addRetryFailedPolicy = false;
  }

  onRunSubJobsPolicyAdded(policy: RunSubJobsPolicy) {
      this.jobTemplate.runSubJobsPolicy = policy;

      this.addRunSubJobsPolicy = false;
  }

  onRunnerAdded(runner: Runner) {
    this.jobTemplate.runner = runner;

    this.addQueueRunner = false;
    this.addHttpRunner = false;
  }

  onRemoveTrigger(trigger: Trigger)
  {
    this.jobTemplate.removeTrigger(trigger);
  }

  onRemoveExclusivePolicy() {
    this.jobTemplate.exclusivePolicy = null;
  }

  onRemoveGracePeriodPolicy() {
      this.jobTemplate.gracePeriodPolicy = null;
  }

  onRemoveRetryFailedPolicy() {
      this.jobTemplate.retryFailedPolicy = null;
  }

  onRemoveRunSubJobsPolicy() {
      this.jobTemplate.runSubJobsPolicy = null;
  }

  onRemoveRunner() {
    this.jobTemplate.runner = null;
  }
}

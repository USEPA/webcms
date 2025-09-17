# Monitoring and Observability

## Overview

This document covers monitoring, logging, and health checks for the WebCMS on ECS Fargate.

## Stack Components

| Component | Purpose |
|-----------|---------|
| CloudWatch Logs | Application, nginx, and Drush task logs |
| CloudWatch Metrics / Container Insights | ECS service/task-level metrics |
| ALB Target Health | Load balancer health for services |
| Optional APM (e.g., New Relic) | Application performance monitoring |

Note: CI/CD is GitLab; monitoring is AWS-native irrespective of CI.

## CloudWatch Logs

- Each ECS task streams stdout/stderr to CloudWatch Logs
- Service/task log group naming follows Terraform configuration (commonly /aws/ecs/webcms-${environment}/{service})
- ALB access logs can be sent to S3 if configured

## Container Insights (ECS)

- Enabled at cluster level (Container Insights)
- Provides CPU, memory, network, and task counts per service
- Use CloudWatch dashboards for quick triage

## Key Metrics to Watch

- Application error rate (from logs)
- ALB 4xx/5xx rates
- Task CPU/Memory utilization (Container Insights)
- Latency percentiles (if APM enabled)

## Health Checks

- ALB target groups monitor container health endpoints
- Ensure application exposes a lightweight health endpoint (e.g., /health)

## APM (Optional)

If using APM (e.g., New Relic), configure via environment variables or ini files in the image. Suggested naming: WebCMS-${environment}-${site}-${lang}.

## Logs Insights Examples

Error analysis:

```sql
fields @timestamp, @message, @logStream
| filter @message like /ERROR|CRITICAL/
| stats count() by bin(5m)
| sort @timestamp desc
```

Latency analysis (if logged):

```sql
fields @timestamp, @message
| filter @message like /response_time/
| extract @message /response_time:(?<rt>\d+)/
| stats avg(rt), max(rt) by bin(5m)
```

## Runbooks (Quick)

- Elevated error rate:
  1) Check ECS service for recent task restarts and events
  2) Review application logs for stack traces
  3) Verify DB/cache connectivity
  4) Roll back to prior task definition if needed

- High memory/CPU:
  1) Inspect Container Insights for the service
  2) Increase task size or add tasks (scale out)
  3) Optimize workload and caching

- Failing targets on ALB:
  1) Check target group health description
  2) Inspect container logs for startup/fatal errors
  3) Verify health check path and security groups

### Error Rate Issues  

**Alert**: Error rate > 5%
**Investigation Steps**:

1. Check error logs in CloudWatch
2. Review New Relic error analytics
3. Check database connectivity
4. Verify external service dependencies
5. Review recent configuration changes

**Resolution Actions**:

- Fix application bugs
- Restore database connectivity
- Rollback problematic deployments
- Update external service configurations

### Resource Usage Issues

**Alert**: Memory usage > 80%
**Investigation Steps**:

1. Check pod resource usage
2. Review memory allocation patterns
3. Check for memory leaks
4. Verify resource limits
5. Review application memory usage

**Resolution Actions**:

- Increase memory limits
- Scale pods horizontally
- Optimize application memory usage
- Restart affected pods

## Monitoring Best Practices

### Metric Collection

- Use consistent naming conventions
- Tag resources appropriately
- Set appropriate collection intervals
- Monitor both system and business metrics

### Alerting

- Set meaningful thresholds
- Use multiple evaluation periods
- Configure appropriate notification channels
- Include context in alert messages

### Log Management

- Use structured logging
- Include correlation IDs
- Set appropriate retention periods
- Implement log sampling for high-volume environments

### Dashboard Design

- Focus on key metrics
- Use meaningful visualizations
- Include trend analysis
- Provide drill-down capabilities

## Maintenance Tasks

### Daily Tasks

- Review alert notifications
- Check dashboard health
- Monitor resource usage trends
- Verify backup completion

### Weekly Tasks  

- Review log retention policies
- Analyze performance trends
- Update monitoring configurations
- Test alert mechanisms

### Monthly Tasks

- Review and optimize dashboards
- Update monitoring documentation
- Analyze cost optimization opportunities
- Review monitoring coverage gaps

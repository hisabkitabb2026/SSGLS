# InvoiceShelf Production Deployment Guide

## Pre-Deployment Checklist

### Code Quality
- [ ] composer lint passes without errors
- [ ] pnpm lint passes with --max-warnings 0
- [ ] pnpm typecheck passes with strict mode
- [ ] php artisan test --compact all tests pass
- [ ] No console.log, dd(), or debug code in production build
- [ ] All sensitive data moved to environment variables

### Performance
- [ ] Bundle size < 250KB gzipped (main chunk)
- [ ] No N+1 queries in critical paths
- [ ] Database indexes created for transport fields
- [ ] Redis/cache properly configured
- [ ] Lighthouse score > 85

### Security
- [ ] No API keys in .env.example
- [ ] CompanySetting sensitive values encrypted
- [ ] File uploads validated (type, size, MIME)
- [ ] Rate limiting enabled on all mutations
- [ ] CORS properly configured
- [ ] CSRF protection enabled
- [ ] Security headers middleware added
- [ ] No SQL injection vulnerabilities

### Infrastructure
- [ ] All migrations tested on production-sized data (1M+ rows)
- [ ] Migration lock times < 30 seconds per batch
- [ ] Database backups configured
- [ ] Monitoring/alerting setup
- [ ] Error tracking (Sentry/similar) configured
- [ ] Log aggregation configured
- [ ] Health check endpoint working
- [ ] Docker image builds successfully

### Testing
- [ ] Transport/Lorry features fully tested
- [ ] Feature tests pass with real database
- [ ] Integration tests pass
- [ ] User flows tested in browser
- [ ] Error paths tested
- [ ] Rollback plan documented and tested

---

## Pre-Flight Deployment Steps

### 1. Code Freeze & Testing (1 hour)
bash
# Ensure latest code
git fetch origin main

# Run full test suite
php artisan test --parallel

# Type check
pnpm typecheck

# Lint check
vendor/bin/pint --test
pnpm lint --max-warnings 0

# Build frontend
pnpm build

# Verify production build
pnpm preview


### 2. Database Preparation (2 hours)
bash
# Backup production database
mysqldump -u root invoiceshelf > backups/invoiceshelf-$(date
%Y%m%d).sql

# Test migrations on staging (1M+ rows)
# Run each migration separately:
php artisan migrate --step --database=staging

# Monitor lock times (should be < 30 seconds per migration)
mysql -u root invoiceshelf -e "SHOW PROCESSLIST;" # Check for locks

# Verify data integrity
php artisan db:seed --class=ProductionDataVerificationSeeder


### 3. Configuration Review
bash
# Verify all environment variables
cat .env | grep -E "DB_|CACHE_|REDIS_|MAIL_|AI_"

# Check sensitive configs are set
grep -E "ENCRYPTION_KEY|APP_KEY" .env

# Verify file storage configuration
php artisan tinker
> FileDisk::all() # Should show configured disks

# Check cache driver
cache()->put('test', 'value')
cache()->get('test') # Should return 'value'


### 4. Feature Verification
```bash
# Test transport features via API
curl -X POST http://localhost/api/v1/invoices \
-H "Authorization: Bearer {token}" \
-H "company: {company_id}" \
-d '{"template_name": "lorry_receipt", "docket_no": "TEST-001", ...}'

# Verify PDF generation +php artisan tinker +> Invoice::first()->generatePdf() # Should succeed + +# Test email sending +Mail::raw('Test', function($message) { $message->to('test@example.com'); }); + + +--- + +## Deployment Strategy: Zero-Downtime + +### Option A: Blue-Green Deployment (Recommended) + +bash +# 1. Deploy new code to staging server +git clone repo /var/www/invoiceshelf-green +cd /var/www/invoiceshelf-green +composer install --no-dev --optimize-autoloader +pnpm ci && pnpm build +cp .env.production .env +php artisan config:cache +php artisan route:cache +php artisan view:cache + +# 2. Run migrations on shared database +php artisan migrate --force --step + +# 3. Warm up caches +php artisan cache:warmup +php artisan queue:restart + +# 4. Health check new instance +curl -f http://invoiceshelf-green/health || exit 1 + +# 5. Switch load balancer (nginx/HAProxy) +# Point traffic from invoiceshelf-blue to invoiceshelf-green + +# 6. Keep old version as fallback +# If issues detected, point back to invoiceshelf-blue + + +### Option B: Rolling Deployment (High Availability) + +For multi-server setups: +bash +# For each server sequentially: +# 1. Remove from load balancer +# 2. Deploy code + run migrations +# 3. Verify health checks +# 4. Add back to load balancer +# 5. Wait for in-flight requests to complete + + +--- + +## Deployment Execution + +### Phase 1: Pre-Migration Tasks (0 downtime) +bash +# Deploy code without restarting +git pull origin main +composer install --no-dev --optimize-autoloader +pnpm ci && pnpm build +php artisan config:cache +php artisan route:cache +php artisan view:cache + +# Pre-cache routes and configs +php artisan cache:warmup + +# Clear queue +php artisan queue:restart + + +### Phase 2: Run Migrations (minimal downtime: <30 seconds per batch) +bash +# Each batch runs quickly (< 30 seconds table lock) +php artisan migrate --step --force --batch=1 + +# Monitor with: +# In another terminal: WATCH -n 1 'SHOW PROCESSLIST;' | grep ALTER + + +### Phase 3: Post-Migration Tasks +bash +# Clear caches +php artisan cache:clear +php artisan view:clear + +# Restart queue workers +php artisan queue:restart + +# Verify data integrity +php artisan db:seed --class=DataIntegrityVerificationSeeder + +# Tail logs for errors +tail -f storage/logs/laravel.log + + +--- + +## Monitoring & Verification + +### Immediate (First 5 minutes) +bash +# Check error logs +tail -f storage/logs/laravel.log | grep -i error + +# Monitor slow queries +SHOW PROCESSLIST; # Should see no long-running queries + +# Check queue status +php artisan queue:monitor + +# Test key features +curl http://localhost/api/v1/bootstrap +curl http://localhost/api/v1/invoices?page=1 + + +### Short-term (First hour) +- [ ] No error spikes in error tracking (Sentry) +- [ ] Response times normal (< 200ms P95) +- [ ] CPU/Memory usage normal +- [ ] Database query times normal +- [ ] Queue processing normally +- [ ] Cache hit ratio > 80% + +### Medium-term (First day) +- [ ] All transport features working +- [ ] PDF generation succeeding +- [ ] Emails sending correctly +- [ ] No 500 errors in logs +- [ ] No N+1 query alerts +- [ ] No rate limiting false positives + +--- + +## Rollback Plan + +### Quick Rollback (< 2 minutes) +bash +# Option 1: Switch load balancer to previous version +# Point traffic back to invoiceshelf-blue + +# Option 2: Revert code +git revert HEAD +git push origin main +# Then redeploy (1 minute) + +# Option 3: Use database backup +mysqldump invoiceshelf > current.backup.sql +mysql invoiceshelf < backups/invoiceshelf-20240731.sql + +# Verify rollback +curl http://localhost/api/v1/bootstrap + + +### Full Rollback Procedure (if needed) +1. Switch to previous code version +2. Restore database from backup +3. Clear all caches +4. Restart queue workers +5. Verify all systems +6. Notify users of any data loss +7. Post-mortem analysis + +--- + +## Post-Deployment + +### Day 1 Verification +- [ ] All users can login +- [ ] All invoice types accessible +- [ ] Transport features working +- [ ] PDF generation working +- [ ] Email notifications working +- [ ] No critical errors in logs +- [ ] Performance metrics stable + +### Day 7 Verification +- [ ] No issues reported by users +- [ ] Database performance stable +- [ ] Error rate < 0.1% +- [ ] Response times stable +- [ ] Cache hit ratio stable +- [ ] Backup strategy working + +### Update Documentation +- [ ] Update version number +- [ ] Document deployment process +- [ ] Document any issues encountered +- [ ] Update runbooks +- [ ] Update architectural decisions + +--- + +## Troubleshooting + +### Issue: Migrations failing +bash +# Check migration status +php artisan migrate:status + +# Rollback specific migration +php artisan migrate:rollback --step=1 + +# Check error logs +tail storage/logs/laravel.log + +# Manual fix (if needed) +mysql invoiceshelf -e "ALTER TABLE invoices ADD COLUMN ..." +php artisan migrate:refresh --step + + +### Issue: High memory usage +bash +# Check queue backlog +php artisan queue:monitor + +# Kill stuck workers +pkill -f "artisan queue" + +# Clear cache +php artisan cache:clear + +# Restart with memory limit +php artisan queue:work --memory=512 + + +### Issue: Slow queries +bash +# Enable slow query log +SET GLOBAL slow_query_log = 'ON'; +SET GLOBAL long_query_time = 1; + +# Check slow queries +SHOW FULL PROCESSLIST; + +# Add missing indexes +php artisan db:seed --class=AddMissingIndexesSeeder + + +### Issue: Auth errors +bash +# Regenerate API tokens +php artisan tinker +> User::all()->each->tokens()->delete() + +# Clear session cache +php artisan cache:clear --tag=sessions + +# Verify auth middleware +php artisan route:list | grep auth +``` + +--- + +## Maintenance Windows + +### Planned Maintenance +- Schedule during low-traffic hours (2-4 AM local time) +- Notify users 48 hours in advance +- Estimated downtime: 10-15 minutes +- Keep rollback ready for 1 hour after deployment + +### Emergency Patches +- Can be deployed anytime +- Use blue-green for zero-downtime +- Document reason and changes +- Notify security team if applicable + +--- + +## Success Metrics + +Deployment is successful when: +1. ✅ All tests pass +2. ✅ Zero critical errors in first hour +3. ✅ < 1% error rate after 24 hours +4. ✅ Response times < 200ms P95 +5. ✅ Database queries < 100ms P95 +6. ✅ Transport features fully functional +7. ✅ All users report normal operation + +--- + +## Contact & Escalation + +- *On-call Engineer**: [contact] +- Database Admin: [contact] +- Security Lead: [contact] +- Incident Channel: #incidents on Slack + +For issues during deployment: +1. Check logs: tail -f storage/logs/laravel.log +2. Monitor metrics: Datadog/New Relic dashboard +3. Contact on-call engineer if P1 issue +4. Prepare rollback if necessary
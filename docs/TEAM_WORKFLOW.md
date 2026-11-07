# GitHub Team Workflow

## Simple workflow
1. Create a GitHub Issue for one specific task or bug; write what success looks like.
2. Agree on the task owner and priority in sprint planning.
3. Update local `main`: `git checkout main` then `git pull origin main`.
4. Make a short-lived branch: `git checkout -b feature/short-description` (or `fix/short-description`).
5. Commit focused changes: `git add .` then `git commit -m "Describe what changed"`.
6. Push: `git push -u origin feature/short-description`.
7. Open a Pull Request to `main`; have a teammate review and test it before merging.
8. Close or link the Issue and update its status when the work is completed.

## Suggested issue stages
**To Do → In Progress → In Review → Done**

## Ground rules
- Do not commit directly to `main` for regular feature work.
- Do not commit passwords, credentials, customer details, or unreviewed production data.
- Agree on shared database changes before changing table/column names.
- Keep one source of truth for payment and inventory records.
- Ask for help when blocked; raising blockers early is useful, not a failure.
- Do not call a feature Done until it is tested and meets the team's acceptance criteria.
- Use short daily check-ins if practical, focused on progress toward the Sprint Goal and blockers.

## Scrum reminders
The Scrum Master helps the team use Scrum and remove impediments. The Product Owner is accountable for Product Backlog ordering; developers plan how to complete selected work. Everyone should participate in planning and review.

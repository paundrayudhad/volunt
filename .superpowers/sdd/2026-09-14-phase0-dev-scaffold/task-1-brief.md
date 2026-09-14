### Task 1: Git init + baseline commit

**Files:**
- Create: repo git di `D:\volunt`
- Test: `git log --oneline`, `git status --porcelain`

**Interfaces:**
- Consumes: dokumen pra-coding yang sudah ada + file rencana ini.
- Produces: repo bersih dengan commit baseline; semua task berikut commit di atasnya.

- [ ] **Step 1: Init repo dan commit baseline**

```bash
git init
git add README.md PRD.md ARCHITECTURE.md DATABASE.md SECURITY.md TESTING.md API.md DEPLOYMENT.md docs/superpowers/plans/2026-09-14-phase0-dev-scaffold.md webvolunteer-prompt.md
git commit -m "docs: pre-coding spec + phase 0 plan"
```

- [ ] **Step 2: Verifikasi repo bersih**

Run: `git log --oneline -n 1; git status --porcelain`
Expected: satu baris commit `docs: pre-coding spec + phase 0 plan`, status kosong.

---


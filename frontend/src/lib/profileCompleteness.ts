import type { CandidateProfile } from "@/types/candidate";

/**
 * Calculate profile completeness as a percentage (0–100).
 *
 * The 10 fields considered:
 * 1. name
 * 2. phone
 * 3. location
 * 4. professional_summary
 * 5. linkedin_url
 * 6. github_url
 * 7. portfolio_url
 * 8. ≥1 work history entry
 * 9. ≥1 education entry
 * 10. ≥1 skill
 */
export function calculateProfileCompleteness(
  profile: CandidateProfile
): number {
  let filled = 0;
  const total = 10;

  if (profile.name) filled++;
  if (profile.phone) filled++;
  if (profile.location) filled++;
  if (profile.professional_summary) filled++;
  if (profile.linkedin_url) filled++;
  if (profile.github_url) filled++;
  if (profile.portfolio_url) filled++;
  if (profile.work_history && profile.work_history.length > 0) filled++;
  if (profile.education && profile.education.length > 0) filled++;
  if (profile.skills && profile.skills.length > 0) filled++;

  return Math.round((filled / total) * 100);
}

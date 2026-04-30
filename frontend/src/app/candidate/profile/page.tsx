"use client";

import { useState, useEffect, useCallback } from "react";
import { candidateApiClient } from "@/lib/candidateApi";
import { ApiRequestError } from "@/lib/api";
import { Button } from "@/components/ui/Button";
import type {
  CandidateProfile,
  WorkHistory,
  Education,
  Skill,
} from "@/types/candidate";

// ---------------------------------------------------------------------------
// Personal Info Section
// ---------------------------------------------------------------------------

function PersonalInfoSection({
  profile,
  onSaved,
}: {
  profile: CandidateProfile;
  onSaved: () => void;
}) {
  const [form, setForm] = useState({
    name: profile.name,
    phone: profile.phone ?? "",
    location: profile.location ?? "",
    linkedin_url: profile.linkedin_url ?? "",
    portfolio_url: profile.portfolio_url ?? "",
  });
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState("");
  const [success, setSuccess] = useState(false);

  function handleChange(e: React.ChangeEvent<HTMLInputElement>) {
    setForm((prev) => ({ ...prev, [e.target.name]: e.target.value }));
    setSuccess(false);
  }

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    setSaving(true);
    setError("");
    setSuccess(false);
    try {
      await candidateApiClient.put("/candidate/profile", form as unknown as Record<string, unknown>);
      setSuccess(true);
      onSaved();
    } catch (err) {
      setError(err instanceof ApiRequestError ? err.message : "Failed to save.");
    } finally {
      setSaving(false);
    }
  }

  return (
    <section aria-labelledby="personal-info-heading" className="bg-white rounded-lg border border-gray-200 p-6">
      <h2 id="personal-info-heading" className="text-lg font-semibold text-gray-900 mb-4">Personal Information</h2>
      <form onSubmit={handleSubmit} className="space-y-4">
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label htmlFor="pi-name" className="block text-sm font-medium text-gray-700 mb-1">Full Name <span className="text-red-600" aria-hidden="true">*</span></label>
            <input id="pi-name" name="name" value={form.name} onChange={handleChange} required className="block w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-teal-500" />
          </div>
          <div>
            <label htmlFor="pi-email" className="block text-sm font-medium text-gray-700 mb-1">Email</label>
            <input id="pi-email" value={profile.email} disabled className="block w-full rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-500 cursor-not-allowed" />
          </div>
          <div>
            <label htmlFor="pi-phone" className="block text-sm font-medium text-gray-700 mb-1">Phone</label>
            <input id="pi-phone" name="phone" value={form.phone} onChange={handleChange} className="block w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-teal-500" />
          </div>
          <div>
            <label htmlFor="pi-location" className="block text-sm font-medium text-gray-700 mb-1">Location</label>
            <input id="pi-location" name="location" value={form.location} onChange={handleChange} placeholder="e.g. San Francisco, CA" className="block w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-teal-500" />
          </div>
          <div>
            <label htmlFor="pi-linkedin" className="block text-sm font-medium text-gray-700 mb-1">LinkedIn URL</label>
            <input id="pi-linkedin" name="linkedin_url" type="url" value={form.linkedin_url} onChange={handleChange} placeholder="https://linkedin.com/in/..." className="block w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-teal-500" />
          </div>
          <div>
            <label htmlFor="pi-portfolio" className="block text-sm font-medium text-gray-700 mb-1">Portfolio URL</label>
            <input id="pi-portfolio" name="portfolio_url" type="url" value={form.portfolio_url} onChange={handleChange} placeholder="https://..." className="block w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-teal-500" />
          </div>
        </div>
        {error && <div role="alert" className="text-sm text-red-600">{error}</div>}
        {success && <div role="status" className="text-sm text-green-600">Personal information saved.</div>}
        <Button type="submit" variant="primary" loading={saving}>Save Personal Info</Button>
      </form>
    </section>
  );
}

// ---------------------------------------------------------------------------
// Work History Section
// ---------------------------------------------------------------------------

function WorkHistorySection({
  items,
  onSaved,
}: {
  items: WorkHistory[];
  onSaved: () => void;
}) {
  const [editing, setEditing] = useState<string | null>(null);
  const [adding, setAdding] = useState(false);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState("");

  const emptyForm = { job_title: "", company_name: "", start_date: "", end_date: "", description: "" };
  const [form, setForm] = useState(emptyForm);

  function handleChange(e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement>) {
    setForm((prev) => ({ ...prev, [e.target.name]: e.target.value }));
  }

  function startAdd() {
    setForm(emptyForm);
    setAdding(true);
    setEditing(null);
    setError("");
  }

  function startEdit(item: WorkHistory) {
    setForm({
      job_title: item.job_title,
      company_name: item.company_name,
      start_date: item.start_date,
      end_date: item.end_date ?? "",
      description: item.description,
    });
    setEditing(item.id);
    setAdding(false);
    setError("");
  }

  function cancel() {
    setAdding(false);
    setEditing(null);
    setError("");
  }

  async function handleSave(e: React.FormEvent) {
    e.preventDefault();
    setSaving(true);
    setError("");
    const payload = {
      ...form,
      end_date: form.end_date || null,
    };
    try {
      if (editing) {
        await candidateApiClient.put(`/candidate/profile/work-history/${editing}`, payload as unknown as Record<string, unknown>);
      } else {
        await candidateApiClient.post("/candidate/profile/work-history", payload as unknown as Record<string, unknown>);
      }
      cancel();
      onSaved();
    } catch (err) {
      setError(err instanceof ApiRequestError ? err.message : "Failed to save.");
    } finally {
      setSaving(false);
    }
  }

  async function handleDelete(id: string) {
    if (!confirm("Delete this work history entry?")) return;
    try {
      await candidateApiClient.del(`/candidate/profile/work-history/${id}`);
      onSaved();
    } catch (err) {
      setError(err instanceof ApiRequestError ? err.message : "Failed to delete.");
    }
  }

  const showForm = adding || editing;

  return (
    <section aria-labelledby="work-history-heading" className="bg-white rounded-lg border border-gray-200 p-6">
      <div className="flex items-center justify-between mb-4">
        <h2 id="work-history-heading" className="text-lg font-semibold text-gray-900">Work History</h2>
        {!showForm && <Button size="sm" onClick={startAdd}>Add Entry</Button>}
      </div>

      {showForm && (
        <form onSubmit={handleSave} className="space-y-3 mb-4 p-4 bg-gray-50 rounded-md border border-gray-200">
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <label htmlFor="wh-title" className="block text-sm font-medium text-gray-700 mb-1">Job Title *</label>
              <input id="wh-title" name="job_title" value={form.job_title} onChange={handleChange} required className="block w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-teal-500" />
            </div>
            <div>
              <label htmlFor="wh-company" className="block text-sm font-medium text-gray-700 mb-1">Company *</label>
              <input id="wh-company" name="company_name" value={form.company_name} onChange={handleChange} required className="block w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-teal-500" />
            </div>
            <div>
              <label htmlFor="wh-start" className="block text-sm font-medium text-gray-700 mb-1">Start Date *</label>
              <input id="wh-start" name="start_date" type="date" value={form.start_date} onChange={handleChange} required className="block w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-teal-500" />
            </div>
            <div>
              <label htmlFor="wh-end" className="block text-sm font-medium text-gray-700 mb-1">End Date</label>
              <input id="wh-end" name="end_date" type="date" value={form.end_date} onChange={handleChange} className="block w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-teal-500" />
              <p className="text-xs text-gray-500 mt-1">Leave blank for current position</p>
            </div>
          </div>
          <div>
            <label htmlFor="wh-desc" className="block text-sm font-medium text-gray-700 mb-1">Description</label>
            <textarea id="wh-desc" name="description" value={form.description} onChange={handleChange} rows={3} className="block w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-teal-500" />
          </div>
          {error && <div role="alert" className="text-sm text-red-600">{error}</div>}
          <div className="flex gap-2">
            <Button type="submit" loading={saving}>{editing ? "Update" : "Add"}</Button>
            <Button type="button" variant="secondary" onClick={cancel}>Cancel</Button>
          </div>
        </form>
      )}

      {items.length === 0 && !showForm && (
        <p className="text-sm text-gray-500">No work history entries yet.</p>
      )}

      <ul className="space-y-3">
        {items.map((item) => (
          <li key={item.id} className="flex items-start justify-between p-3 rounded-md border border-gray-100 hover:bg-gray-50">
            <div className="min-w-0">
              <p className="text-sm font-medium text-gray-900">{item.job_title}</p>
              <p className="text-sm text-gray-600">{item.company_name}</p>
              <p className="text-xs text-gray-500">{item.start_date} — {item.end_date ?? "Present"}</p>
              {item.description && <p className="text-xs text-gray-500 mt-1 line-clamp-2">{item.description}</p>}
            </div>
            <div className="flex gap-1 ml-2 flex-shrink-0">
              <button type="button" onClick={() => startEdit(item)} className="text-xs text-teal-600 hover:text-teal-800 px-2 py-1 rounded hover:bg-teal-50 focus:outline-none focus:ring-2 focus:ring-teal-500" aria-label={`Edit ${item.job_title}`}>Edit</button>
              <button type="button" onClick={() => handleDelete(item.id)} className="text-xs text-red-600 hover:text-red-800 px-2 py-1 rounded hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-500" aria-label={`Delete ${item.job_title}`}>Delete</button>
            </div>
          </li>
        ))}
      </ul>
    </section>
  );
}

// ---------------------------------------------------------------------------
// Education Section
// ---------------------------------------------------------------------------

function EducationSection({
  items,
  onSaved,
}: {
  items: Education[];
  onSaved: () => void;
}) {
  const [editing, setEditing] = useState<string | null>(null);
  const [adding, setAdding] = useState(false);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState("");

  const emptyForm = { institution_name: "", degree: "", field_of_study: "", start_date: "", end_date: "" };
  const [form, setForm] = useState(emptyForm);

  function handleChange(e: React.ChangeEvent<HTMLInputElement>) {
    setForm((prev) => ({ ...prev, [e.target.name]: e.target.value }));
  }

  function startAdd() {
    setForm(emptyForm);
    setAdding(true);
    setEditing(null);
    setError("");
  }

  function startEdit(item: Education) {
    setForm({
      institution_name: item.institution_name,
      degree: item.degree,
      field_of_study: item.field_of_study,
      start_date: item.start_date,
      end_date: item.end_date ?? "",
    });
    setEditing(item.id);
    setAdding(false);
    setError("");
  }

  function cancel() {
    setAdding(false);
    setEditing(null);
    setError("");
  }

  async function handleSave(e: React.FormEvent) {
    e.preventDefault();
    setSaving(true);
    setError("");
    const payload = { ...form, end_date: form.end_date || null };
    try {
      if (editing) {
        await candidateApiClient.put(`/candidate/profile/education/${editing}`, payload as unknown as Record<string, unknown>);
      } else {
        await candidateApiClient.post("/candidate/profile/education", payload as unknown as Record<string, unknown>);
      }
      cancel();
      onSaved();
    } catch (err) {
      setError(err instanceof ApiRequestError ? err.message : "Failed to save.");
    } finally {
      setSaving(false);
    }
  }

  async function handleDelete(id: string) {
    if (!confirm("Delete this education entry?")) return;
    try {
      await candidateApiClient.del(`/candidate/profile/education/${id}`);
      onSaved();
    } catch (err) {
      setError(err instanceof ApiRequestError ? err.message : "Failed to delete.");
    }
  }

  const showForm = adding || editing;

  return (
    <section aria-labelledby="education-heading" className="bg-white rounded-lg border border-gray-200 p-6">
      <div className="flex items-center justify-between mb-4">
        <h2 id="education-heading" className="text-lg font-semibold text-gray-900">Education</h2>
        {!showForm && <Button size="sm" onClick={startAdd}>Add Entry</Button>}
      </div>

      {showForm && (
        <form onSubmit={handleSave} className="space-y-3 mb-4 p-4 bg-gray-50 rounded-md border border-gray-200">
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <label htmlFor="ed-inst" className="block text-sm font-medium text-gray-700 mb-1">Institution *</label>
              <input id="ed-inst" name="institution_name" value={form.institution_name} onChange={handleChange} required className="block w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-teal-500" />
            </div>
            <div>
              <label htmlFor="ed-degree" className="block text-sm font-medium text-gray-700 mb-1">Degree *</label>
              <input id="ed-degree" name="degree" value={form.degree} onChange={handleChange} required className="block w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-teal-500" />
            </div>
            <div>
              <label htmlFor="ed-field" className="block text-sm font-medium text-gray-700 mb-1">Field of Study *</label>
              <input id="ed-field" name="field_of_study" value={form.field_of_study} onChange={handleChange} required className="block w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-teal-500" />
            </div>
            <div>{/* spacer */}</div>
            <div>
              <label htmlFor="ed-start" className="block text-sm font-medium text-gray-700 mb-1">Start Date *</label>
              <input id="ed-start" name="start_date" type="date" value={form.start_date} onChange={handleChange} required className="block w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-teal-500" />
            </div>
            <div>
              <label htmlFor="ed-end" className="block text-sm font-medium text-gray-700 mb-1">End Date</label>
              <input id="ed-end" name="end_date" type="date" value={form.end_date} onChange={handleChange} className="block w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-teal-500" />
            </div>
          </div>
          {error && <div role="alert" className="text-sm text-red-600">{error}</div>}
          <div className="flex gap-2">
            <Button type="submit" loading={saving}>{editing ? "Update" : "Add"}</Button>
            <Button type="button" variant="secondary" onClick={cancel}>Cancel</Button>
          </div>
        </form>
      )}

      {items.length === 0 && !showForm && (
        <p className="text-sm text-gray-500">No education entries yet.</p>
      )}

      <ul className="space-y-3">
        {items.map((item) => (
          <li key={item.id} className="flex items-start justify-between p-3 rounded-md border border-gray-100 hover:bg-gray-50">
            <div className="min-w-0">
              <p className="text-sm font-medium text-gray-900">{item.degree} in {item.field_of_study}</p>
              <p className="text-sm text-gray-600">{item.institution_name}</p>
              <p className="text-xs text-gray-500">{item.start_date} — {item.end_date ?? "Present"}</p>
            </div>
            <div className="flex gap-1 ml-2 flex-shrink-0">
              <button type="button" onClick={() => startEdit(item)} className="text-xs text-teal-600 hover:text-teal-800 px-2 py-1 rounded hover:bg-teal-50 focus:outline-none focus:ring-2 focus:ring-teal-500" aria-label={`Edit ${item.degree}`}>Edit</button>
              <button type="button" onClick={() => handleDelete(item.id)} className="text-xs text-red-600 hover:text-red-800 px-2 py-1 rounded hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-500" aria-label={`Delete ${item.degree}`}>Delete</button>
            </div>
          </li>
        ))}
      </ul>
    </section>
  );
}

// ---------------------------------------------------------------------------
// Skills Section
// ---------------------------------------------------------------------------

function SkillsSection({
  items,
  onSaved,
}: {
  items: Skill[];
  onSaved: () => void;
}) {
  const [newSkill, setNewSkill] = useState("");
  const [category, setCategory] = useState<"technical" | "soft">("technical");
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState("");

  async function addSkill(e: React.FormEvent) {
    e.preventDefault();
    if (!newSkill.trim()) return;
    setSaving(true);
    setError("");
    const updatedSkills = [
      ...items.map((s) => ({ name: s.name, category: s.category })),
      { name: newSkill.trim(), category },
    ];
    try {
      await candidateApiClient.put("/candidate/profile/skills", {
        skills: updatedSkills,
      } as unknown as Record<string, unknown>);
      setNewSkill("");
      onSaved();
    } catch (err) {
      setError(err instanceof ApiRequestError ? err.message : "Failed to save.");
    } finally {
      setSaving(false);
    }
  }

  async function removeSkill(name: string) {
    setSaving(true);
    setError("");
    const updatedSkills = items
      .filter((s) => s.name !== name)
      .map((s) => ({ name: s.name, category: s.category }));
    try {
      await candidateApiClient.put("/candidate/profile/skills", {
        skills: updatedSkills,
      } as unknown as Record<string, unknown>);
      onSaved();
    } catch (err) {
      setError(err instanceof ApiRequestError ? err.message : "Failed to remove skill.");
    } finally {
      setSaving(false);
    }
  }

  const technicalSkills = items.filter((s) => s.category === "technical");
  const softSkills = items.filter((s) => s.category === "soft");

  return (
    <section aria-labelledby="skills-heading" className="bg-white rounded-lg border border-gray-200 p-6">
      <h2 id="skills-heading" className="text-lg font-semibold text-gray-900 mb-4">Skills</h2>

      <form onSubmit={addSkill} className="flex flex-col sm:flex-row gap-2 mb-4">
        <input
          value={newSkill}
          onChange={(e) => setNewSkill(e.target.value)}
          placeholder="Add a skill…"
          aria-label="New skill name"
          className="flex-1 rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-teal-500"
        />
        <select
          value={category}
          onChange={(e) => setCategory(e.target.value as "technical" | "soft")}
          aria-label="Skill category"
          className="rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-teal-500"
        >
          <option value="technical">Technical</option>
          <option value="soft">Soft Skill</option>
        </select>
        <Button type="submit" size="sm" loading={saving}>Add</Button>
      </form>

      {error && <div role="alert" className="text-sm text-red-600 mb-3">{error}</div>}

      {technicalSkills.length > 0 && (
        <div className="mb-3">
          <h3 className="text-xs font-medium text-gray-500 uppercase tracking-wide mb-2">Technical</h3>
          <div className="flex flex-wrap gap-2">
            {technicalSkills.map((skill) => (
              <span key={skill.id} className="inline-flex items-center gap-1 rounded-full bg-teal-50 text-teal-700 px-3 py-1 text-sm">
                {skill.name}
                <button type="button" onClick={() => removeSkill(skill.name)} className="ml-1 text-teal-500 hover:text-teal-800 focus:outline-none focus:ring-2 focus:ring-teal-500 rounded-full" aria-label={`Remove ${skill.name}`}>
                  <svg className="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor" aria-hidden="true"><path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
              </span>
            ))}
          </div>
        </div>
      )}

      {softSkills.length > 0 && (
        <div>
          <h3 className="text-xs font-medium text-gray-500 uppercase tracking-wide mb-2">Soft Skills</h3>
          <div className="flex flex-wrap gap-2">
            {softSkills.map((skill) => (
              <span key={skill.id} className="inline-flex items-center gap-1 rounded-full bg-purple-50 text-purple-700 px-3 py-1 text-sm">
                {skill.name}
                <button type="button" onClick={() => removeSkill(skill.name)} className="ml-1 text-purple-500 hover:text-purple-800 focus:outline-none focus:ring-2 focus:ring-purple-500 rounded-full" aria-label={`Remove ${skill.name}`}>
                  <svg className="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor" aria-hidden="true"><path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
              </span>
            ))}
          </div>
        </div>
      )}

      {items.length === 0 && <p className="text-sm text-gray-500">No skills added yet.</p>}
    </section>
  );
}

// ---------------------------------------------------------------------------
// Main Profile Page
// ---------------------------------------------------------------------------

export default function CandidateProfilePage() {
  const [profile, setProfile] = useState<CandidateProfile | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");

  const fetchProfile = useCallback(async () => {
    try {
      const response = await candidateApiClient.get<CandidateProfile>("/candidate/profile");
      setProfile(response.data);
    } catch (err) {
      setError(err instanceof ApiRequestError ? err.message : "Failed to load profile.");
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    fetchProfile();
  }, [fetchProfile]);

  if (loading) {
    return (
      <div className="flex items-center justify-center py-12">
        <div className="inline-block h-6 w-6 animate-spin rounded-full border-4 border-teal-600 border-r-transparent" role="status" aria-label="Loading profile" />
        <span className="ml-2 text-sm text-gray-500">Loading profile…</span>
      </div>
    );
  }

  if (error || !profile) {
    return (
      <div role="alert" className="rounded-md bg-red-50 border border-red-200 p-4 text-sm text-red-700">
        {error || "Failed to load profile."}
      </div>
    );
  }

  return (
    <div>
      <div className="mb-6">
        <h1 className="text-2xl font-bold text-gray-900">Edit Profile</h1>
        <p className="mt-1 text-sm text-gray-500">Keep your profile up to date — this data is used to pre-populate new resumes.</p>
      </div>

      <div className="space-y-6">
        <PersonalInfoSection profile={profile} onSaved={fetchProfile} />
        <WorkHistorySection items={profile.work_history} onSaved={fetchProfile} />
        <EducationSection items={profile.education} onSaved={fetchProfile} />
        <SkillsSection items={profile.skills} onSaved={fetchProfile} />
      </div>
    </div>
  );
}

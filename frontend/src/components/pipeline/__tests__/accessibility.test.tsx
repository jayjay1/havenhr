import { describe, it, expect, vi, beforeEach } from "vitest";
import { render, screen, fireEvent } from "@testing-library/react";
import React from "react";
import { getContrastTextColor } from "../StageColumn";

// ---------------------------------------------------------------------------
// Unit tests for getContrastTextColor
// ---------------------------------------------------------------------------

describe("getContrastTextColor", () => {
  it("returns dark text for white background", () => {
    expect(getContrastTextColor("#FFFFFF")).toBe("#111827");
  });

  it("returns white text for black background", () => {
    expect(getContrastTextColor("#000000")).toBe("#FFFFFF");
  });

  it("returns dark text for light yellow", () => {
    expect(getContrastTextColor("#FBBF24")).toBe("#111827");
  });

  it("returns white text for dark blue", () => {
    expect(getContrastTextColor("#1E3A5F")).toBe("#FFFFFF");
  });

  it("returns white text for dark red", () => {
    expect(getContrastTextColor("#7F1D1D")).toBe("#FFFFFF");
  });

  it("returns dark text for light green", () => {
    expect(getContrastTextColor("#86EFAC")).toBe("#111827");
  });
});

// ---------------------------------------------------------------------------
// Unit tests for CandidateCard keyboard navigation
// ---------------------------------------------------------------------------

// We need to mock the modules that CandidateCard depends on
vi.mock("@dnd-kit/core", () => ({
  useDraggable: () => ({
    attributes: {},
    listeners: {},
    setNodeRef: vi.fn(),
    transform: null,
  }),
}));

vi.mock("@/lib/jobApi", () => ({
  moveApplication: vi.fn(() => Promise.resolve()),
}));

// Mock KanbanProvider
const mockDispatch = vi.fn();
const mockState = {
  stages: [
    {
      id: "stage-1",
      name: "Applied",
      color: null,
      sort_order: 0,
      applications: [
        {
          id: "app-1",
          candidate_name: "Alice",
          candidate_email: "alice@test.com",
          current_stage: "stage-1",
          status: "active",
          applied_at: "2024-01-01T00:00:00Z",
        },
        {
          id: "app-2",
          candidate_name: "Bob",
          candidate_email: "bob@test.com",
          current_stage: "stage-1",
          status: "active",
          applied_at: "2024-01-02T00:00:00Z",
        },
      ],
    },
    {
      id: "stage-2",
      name: "Interview",
      color: "#3B82F6",
      sort_order: 1,
      applications: [],
    },
    {
      id: "stage-3",
      name: "Offer",
      color: "#10B981",
      sort_order: 2,
      applications: [],
    },
  ],
  selectedIds: new Set<string>(),
  searchQuery: "",
  stageFilter: null,
  sortBy: "applied_at_desc" as const,
  slideOverAppId: null,
  isLoading: false,
  error: null,
  previousState: null,
  totalCandidates: 2,
};

vi.mock("../KanbanProvider", () => ({
  useKanban: () => ({
    state: mockState,
    dispatch: mockDispatch,
  }),
}));

vi.mock("../BottomSheet", () => ({
  BottomSheet: () => null,
}));

// Import after mocks
import { CandidateCard } from "../CandidateCard";
import { moveApplication } from "@/lib/jobApi";

describe("CandidateCard keyboard navigation", () => {
  const defaultProps = {
    application: mockState.stages[0].applications[0],
    isSelected: false,
    isDragging: false,
    canManage: true,
    onSelect: vi.fn(),
    onClick: vi.fn(),
  };

  beforeEach(() => {
    vi.clearAllMocks();
  });

  it("opens slide-over on Enter key", () => {
    render(<CandidateCard {...defaultProps} />);
    const card = screen.getByRole("article");
    fireEvent.keyDown(card, { key: "Enter" });
    expect(defaultProps.onClick).toHaveBeenCalledWith("app-1");
  });

  it("opens slide-over on Space key", () => {
    render(<CandidateCard {...defaultProps} />);
    const card = screen.getByRole("article");
    fireEvent.keyDown(card, { key: " " });
    expect(defaultProps.onClick).toHaveBeenCalledWith("app-1");
  });

  it("dispatches MOVE_CARD_OPTIMISTIC on Ctrl+ArrowRight", () => {
    render(<CandidateCard {...defaultProps} />);
    const card = screen.getByRole("article");
    fireEvent.keyDown(card, { key: "ArrowRight", ctrlKey: true });

    expect(mockDispatch).toHaveBeenCalledWith({
      type: "MOVE_CARD_OPTIMISTIC",
      appId: "app-1",
      fromStageId: "stage-1",
      toStageId: "stage-2",
    });
    expect(moveApplication).toHaveBeenCalledWith("app-1", "stage-2");
  });

  it("dispatches nothing on Ctrl+ArrowLeft when at first stage", () => {
    render(<CandidateCard {...defaultProps} />);
    const card = screen.getByRole("article");
    fireEvent.keyDown(card, { key: "ArrowLeft", ctrlKey: true });

    expect(mockDispatch).not.toHaveBeenCalled();
    expect(moveApplication).not.toHaveBeenCalled();
  });

  it("does not move card when canManage is false", () => {
    render(<CandidateCard {...defaultProps} canManage={false} />);
    const card = screen.getByRole("article");
    fireEvent.keyDown(card, { key: "ArrowRight", ctrlKey: true });

    expect(mockDispatch).not.toHaveBeenCalled();
    expect(moveApplication).not.toHaveBeenCalled();
  });

  it("focuses next card on ArrowDown", () => {
    const { container } = render(
      <div>
        <CandidateCard {...defaultProps} />
        <CandidateCard
          {...defaultProps}
          application={mockState.stages[0].applications[1]}
        />
      </div>
    );

    const cards = container.querySelectorAll('[role="article"]');
    const firstCard = cards[0] as HTMLElement;
    const secondCard = cards[1] as HTMLElement;

    // Focus the first card
    firstCard.focus();
    fireEvent.keyDown(firstCard, { key: "ArrowDown" });

    expect(document.activeElement).toBe(secondCard);
  });

  it("focuses previous card on ArrowUp", () => {
    const { container } = render(
      <div>
        <CandidateCard {...defaultProps} />
        <CandidateCard
          {...defaultProps}
          application={mockState.stages[0].applications[1]}
        />
      </div>
    );

    const cards = container.querySelectorAll('[role="article"]');
    const firstCard = cards[0] as HTMLElement;
    const secondCard = cards[1] as HTMLElement;

    // Focus the second card
    secondCard.focus();
    fireEvent.keyDown(secondCard, { key: "ArrowUp" });

    expect(document.activeElement).toBe(firstCard);
  });
});

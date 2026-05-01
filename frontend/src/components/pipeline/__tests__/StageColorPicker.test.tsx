import { describe, it, expect, vi, beforeEach } from "vitest";
import { render, screen, fireEvent, waitFor } from "@testing-library/react";
import React from "react";
import { StageColorPicker } from "../StageColorPicker";
import { KanbanProvider } from "../KanbanProvider";

// ---------------------------------------------------------------------------
// Mock pipelineApi
// ---------------------------------------------------------------------------

vi.mock("@/lib/pipelineApi", () => ({
  updatePipelineStage: vi.fn().mockResolvedValue({ data: {} }),
}));

import { updatePipelineStage } from "@/lib/pipelineApi";

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function Wrapper({ children }: { children: React.ReactNode }) {
  return <KanbanProvider>{children}</KanbanProvider>;
}

const defaultProps = {
  stageId: "stage-1",
  jobId: "job-1",
  currentColor: "#3B82F6",
};

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

describe("StageColorPicker", () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  describe("rendering", () => {
    it("renders a toggle button with aria-label", () => {
      render(
        <Wrapper>
          <StageColorPicker {...defaultProps} />
        </Wrapper>
      );
      expect(
        screen.getByRole("button", { name: /pick stage color/i })
      ).toBeInTheDocument();
    });

    it("toggle button shows current color as background", () => {
      render(
        <Wrapper>
          <StageColorPicker {...defaultProps} currentColor="#EF4444" />
        </Wrapper>
      );
      const btn = screen.getByRole("button", { name: /pick stage color/i });
      expect(btn.style.backgroundColor).toBe("rgb(239, 68, 68)");
    });

    it("toggle button shows gray when currentColor is null", () => {
      render(
        <Wrapper>
          <StageColorPicker {...defaultProps} currentColor={null} />
        </Wrapper>
      );
      const btn = screen.getByRole("button", { name: /pick stage color/i });
      expect(btn.style.backgroundColor).toBe("rgb(229, 231, 235)");
    });

    it("popover is not visible by default", () => {
      render(
        <Wrapper>
          <StageColorPicker {...defaultProps} />
        </Wrapper>
      );
      expect(
        screen.queryByRole("dialog", { name: /stage color picker/i })
      ).not.toBeInTheDocument();
    });
  });

  describe("popover behavior", () => {
    it("opens popover on button click", () => {
      render(
        <Wrapper>
          <StageColorPicker {...defaultProps} />
        </Wrapper>
      );
      fireEvent.click(
        screen.getByRole("button", { name: /pick stage color/i })
      );
      expect(
        screen.getByRole("dialog", { name: /stage color picker/i })
      ).toBeInTheDocument();
    });

    it("renders 10 color swatches plus a No color option", () => {
      render(
        <Wrapper>
          <StageColorPicker {...defaultProps} />
        </Wrapper>
      );
      fireEvent.click(
        screen.getByRole("button", { name: /pick stage color/i })
      );

      // 10 color buttons + 1 "No color" button
      const colorButtons = screen.getAllByRole("button").filter(
        (btn) =>
          btn.getAttribute("aria-label")?.match(/blue|green|amber|red|purple|pink|cyan|orange|gray|lime|no color/i)
      );
      expect(colorButtons).toHaveLength(11);
    });

    it("shows checkmark on the currently selected color", () => {
      render(
        <Wrapper>
          <StageColorPicker {...defaultProps} currentColor="#3B82F6" />
        </Wrapper>
      );
      fireEvent.click(
        screen.getByRole("button", { name: /pick stage color/i })
      );

      const blueBtn = screen.getByRole("button", { name: /blue \(selected\)/i });
      expect(blueBtn).toBeInTheDocument();
      // Should contain an SVG checkmark
      expect(blueBtn.querySelector("svg")).toBeInTheDocument();
    });

    it("shows checkmark on No color when currentColor is null", () => {
      render(
        <Wrapper>
          <StageColorPicker {...defaultProps} currentColor={null} />
        </Wrapper>
      );
      fireEvent.click(
        screen.getByRole("button", { name: /pick stage color/i })
      );

      const noColorBtn = screen.getByRole("button", {
        name: /no color \(selected\)/i,
      });
      expect(noColorBtn).toBeInTheDocument();
      expect(noColorBtn.querySelector("svg")).toBeInTheDocument();
    });

    it("closes popover on Escape key", () => {
      render(
        <Wrapper>
          <StageColorPicker {...defaultProps} />
        </Wrapper>
      );
      fireEvent.click(
        screen.getByRole("button", { name: /pick stage color/i })
      );
      expect(
        screen.getByRole("dialog", { name: /stage color picker/i })
      ).toBeInTheDocument();

      fireEvent.keyDown(document, { key: "Escape" });
      expect(
        screen.queryByRole("dialog", { name: /stage color picker/i })
      ).not.toBeInTheDocument();
    });

    it("closes popover on click outside", () => {
      render(
        <Wrapper>
          <div data-testid="outside">Outside</div>
          <StageColorPicker {...defaultProps} />
        </Wrapper>
      );
      fireEvent.click(
        screen.getByRole("button", { name: /pick stage color/i })
      );
      expect(
        screen.getByRole("dialog", { name: /stage color picker/i })
      ).toBeInTheDocument();

      fireEvent.mouseDown(screen.getByTestId("outside"));
      expect(
        screen.queryByRole("dialog", { name: /stage color picker/i })
      ).not.toBeInTheDocument();
    });

    it("toggles popover on repeated button clicks", () => {
      render(
        <Wrapper>
          <StageColorPicker {...defaultProps} />
        </Wrapper>
      );
      const btn = screen.getByRole("button", { name: /pick stage color/i });

      fireEvent.click(btn);
      expect(
        screen.getByRole("dialog", { name: /stage color picker/i })
      ).toBeInTheDocument();

      fireEvent.click(btn);
      expect(
        screen.queryByRole("dialog", { name: /stage color picker/i })
      ).not.toBeInTheDocument();
    });
  });

  describe("color selection", () => {
    it("calls updatePipelineStage API with selected color", async () => {
      render(
        <Wrapper>
          <StageColorPicker {...defaultProps} currentColor={null} />
        </Wrapper>
      );
      fireEvent.click(
        screen.getByRole("button", { name: /pick stage color/i })
      );
      fireEvent.click(screen.getByRole("button", { name: /^red$/i }));

      await waitFor(() => {
        expect(updatePipelineStage).toHaveBeenCalledWith("job-1", "stage-1", {
          color: "#EF4444",
        });
      });
    });

    it("calls updatePipelineStage API with null for No color", async () => {
      render(
        <Wrapper>
          <StageColorPicker {...defaultProps} currentColor="#3B82F6" />
        </Wrapper>
      );
      fireEvent.click(
        screen.getByRole("button", { name: /pick stage color/i })
      );
      fireEvent.click(screen.getByRole("button", { name: /^no color$/i }));

      await waitFor(() => {
        expect(updatePipelineStage).toHaveBeenCalledWith("job-1", "stage-1", {
          color: null,
        });
      });
    });

    it("closes popover after selecting a color", () => {
      render(
        <Wrapper>
          <StageColorPicker {...defaultProps} />
        </Wrapper>
      );
      fireEvent.click(
        screen.getByRole("button", { name: /pick stage color/i })
      );
      fireEvent.click(screen.getByRole("button", { name: /^green$/i }));

      expect(
        screen.queryByRole("dialog", { name: /stage color picker/i })
      ).not.toBeInTheDocument();
    });
  });

  describe("accessibility", () => {
    it("toggle button has aria-expanded attribute", () => {
      render(
        <Wrapper>
          <StageColorPicker {...defaultProps} />
        </Wrapper>
      );
      const btn = screen.getByRole("button", { name: /pick stage color/i });
      expect(btn).toHaveAttribute("aria-expanded", "false");

      fireEvent.click(btn);
      expect(btn).toHaveAttribute("aria-expanded", "true");
    });

    it("toggle button has aria-haspopup attribute", () => {
      render(
        <Wrapper>
          <StageColorPicker {...defaultProps} />
        </Wrapper>
      );
      const btn = screen.getByRole("button", { name: /pick stage color/i });
      expect(btn).toHaveAttribute("aria-haspopup", "true");
    });

    it("popover has role=dialog", () => {
      render(
        <Wrapper>
          <StageColorPicker {...defaultProps} />
        </Wrapper>
      );
      fireEvent.click(
        screen.getByRole("button", { name: /pick stage color/i })
      );
      expect(
        screen.getByRole("dialog", { name: /stage color picker/i })
      ).toBeInTheDocument();
    });

    it("each color swatch has a descriptive aria-label", () => {
      render(
        <Wrapper>
          <StageColorPicker {...defaultProps} currentColor={null} />
        </Wrapper>
      );
      fireEvent.click(
        screen.getByRole("button", { name: /pick stage color/i })
      );

      expect(screen.getByRole("button", { name: /^blue$/i })).toBeInTheDocument();
      expect(screen.getByRole("button", { name: /^green$/i })).toBeInTheDocument();
      expect(screen.getByRole("button", { name: /^amber$/i })).toBeInTheDocument();
      expect(screen.getByRole("button", { name: /^red$/i })).toBeInTheDocument();
      expect(screen.getByRole("button", { name: /^purple$/i })).toBeInTheDocument();
      expect(screen.getByRole("button", { name: /^pink$/i })).toBeInTheDocument();
      expect(screen.getByRole("button", { name: /^cyan$/i })).toBeInTheDocument();
      expect(screen.getByRole("button", { name: /^orange$/i })).toBeInTheDocument();
      expect(screen.getByRole("button", { name: /^gray$/i })).toBeInTheDocument();
      expect(screen.getByRole("button", { name: /^lime$/i })).toBeInTheDocument();
    });
  });
});

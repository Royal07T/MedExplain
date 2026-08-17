import sys
from pathlib import Path

import pytest

sys.path.insert(0, str(Path(__file__).parent))


@pytest.fixture(autouse=True)
def force_stub_provider(monkeypatch):
    from app.services.llm import factory as llm_factory

    class FakeSettings:
        llm_provider = "stub"
        openai_api_key = None

    monkeypatch.setattr(llm_factory, "get_settings", lambda: FakeSettings())
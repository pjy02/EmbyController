import sys
from pocketsphinx import AudioFile

def speech_to_text(audio_path):
    config = {
        'audio_file': audio_path,
    }
    audio = AudioFile(**config)
    for phrase in audio:
        print(phrase)

if __name__ == "__main__":
    audio_path = sys.argv[1]
    speech_to_text(audio_path)

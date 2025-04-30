# Use official image from Pyhton
FROM python:3.12

# Install system dependencies
RUN apt-get update && apt-get install -y \
    portaudio19-dev \
    && rm -rf /var/lib/apt/lists/*

# Create directory inside container
WORKDIR /app

# Copy contents to container
COPY . .

# Install dependencies
RUN pip install --upgrade pip && pip install --no-cache-dir -r requirements.txt

# Command to run app
CMD [ "python", "main.py" ]
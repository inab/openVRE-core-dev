# Installation guide

## VRE Tool dockerization & adaptation guide


Since the Virtual Research environment is a dockerized system, also for tools integration a similar dockerization method is followed, so to encapsulate the tools and their dependencies in a container, allowing for easy sharing, version control, and deployment.

This guide walks through the process of Dockerizing a sequence extraction tool and integrating it into a Virtual Research Environment (VRE) framework.

### Prerequisites

Before proceeding, ensure the following are installed:

- **[Docker](https://docs.docker.com/get-docker/)**: Follow this link to install Docker.
- **[Git](https://git-scm.com/book/en/v2/Getting-Started-Installing-Git)**: Follow this link to install Git.


# Step 1: Creating a Dockerfile for your tool

In this example we are going to use a VTK Viewer tool, an interactive VTK based viewer for 3-dimensional objects.

The Dockerfile sets up the environment by installing dependencies like Biopython and placing the necessary Python script into the container.

**Create the Dockerfile** in your project directory *openVRE/vre_mock_tool/*, that defines the environment and the tool configuration. Using as an example the extraction tool mentioned as before: 

```
FROM ubuntu:20.04

# Set non-interactive mode for apt-get
ENV DEBIAN_FRONTEND=noninteractive
RUN apt-get update && \
    apt-get install -y wget cmake g++ make libxt6 libglu1-mesa libxrender1 \
                       python3 python3-pip libegl1 libegl1-mesa libgl1-mesa-glx \
                       software-properties-common curl libopengl0 libgl1-mesa-dri libglx0 libgl1-mesa-dev

# Add deadsnakes PPA and install Python 3.10
RUN add-apt-repository ppa:deadsnakes/ppa && \
    apt-get update && \
    apt-get install -y python3.10 python3.10-venv python3.10-dev && \
    update-alternatives --install /usr/bin/python3 python3 /usr/bin/python3.8 1 && \
    update-alternatives --install /usr/bin/python3 python3 /usr/bin/python3.10 2 && \
    apt-get clean && \
    rm -rf /var/lib/apt/lists/*

# Install Node Version Manager (NVM) and Node.js
RUN curl -o- https://raw.githubusercontent.com/nvm-sh/nvm/v0.39.3/install.sh | bash && \
    export NVM_DIR="$HOME/.nvm" && \
    [ -s "$NVM_DIR/nvm.sh" ] && \. "$NVM_DIR/nvm.sh" && \
    nvm install 6 && \
    npm install -g pvw-visualizer

# Download and extract ParaView
RUN wget https://www.paraview.org/files/v5.12/ParaView-5.12.1-egl-MPI-Linux-Python3.10-x86_64.tar.gz && \
    tar -xvf ParaView-5.12.1-egl-MPI-Linux-Python3.10-x86_64.tar.gz && \
    rm ParaView-5.12.1-egl-MPI-Linux-Python3.10-x86_64.tar.gz

# Set the working directory
WORKDIR /ParaView-5.12.1-egl-MPI-Linux-Python3.10-x86_64

# Expose the port
EXPOSE 8090

# Command to start the ParaView visualizer
CMD ["./bin/pvpython", "-m", "paraview.apps.visualizer", "--data", "/data", "--port", "8090", "--host", "0.0.0.0"]

```

**Make sure for the ENTRYPOINT to refer directly to the script/software that you would want to launch on the platform.** The VRE framework will use this as the direct command for the wrapper. 
---

**Build the Docker image** once the Dockerfile is set up, with the command:

Remember to change the name of the Dockerfile from *Dockerfile_template* to **Dockerfile** to be able to build the image. 

```bash
docker build -t my_tool_image .
```

In this tool case, the image would be available on **[Docker Hub](https://hub.docker.com/repository/docker/mapoferri/vtk_viewer/general)**
---



## VRE integration:


1.  Adding the Tool in the MongoDB collection;

2. In /volumes/openVRE/tools, make a new directory copying the tool_skeleton one with the name of the tool (same ID that was used in Mongo);

3. Modify the input.php file (*especially the $tool_id*) based on the requirments of the tools (more inputs, more arguments);

4. Modify the /volumes/openVRE/tools/$your_tool/assets/home/ the index.html file, for ur tool to be consinstent with the mongoDB. 


Follow the instructions in detail [here](https://github.com/inab/openVRE/wiki/Register-new-interactive-tool).



## Conclusion

By following these steps, you’ve successfully Dockerized your tool, integrated it with the OpenVRE environment, and configured the necessary Dockerfiles to run the tool in both local and OpenVRE environments.
```
This version is fully formatted as a **Markdown file** with working hyperlinks and a structured list for easy readability. You can now use this as an installation guide for your project.

```
